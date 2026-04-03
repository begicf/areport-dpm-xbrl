<?php

namespace AReportDpmXBRL\Creat;

use AReportDpmXBRL\Config\Config;
use RuntimeException;
use ZipArchive;

class CreateXBRLCsvPackage
{
    private $period;
    private $entityId;
    private $modulePath;
    private $facts;
    private $moduleDir;

    private $moduleJsonPath;
    private $moduleJsonUrl;
    private $moduleMetadata = [];
    private $tableMetadataPaths = [];
    private $tableMetadataCache = [];

    public function __construct($period, $entityId, $modulePath, array $facts, $moduleDir = null)
    {
        $this->period = $period;
        $this->entityId = $entityId;
        $this->modulePath = $modulePath;
        $this->facts = $facts;
        $this->moduleDir = $moduleDir;
    }

    public function writePackage(): array
    {
        $this->bootMetadata();

        $tables = $this->buildTableFiles();

        if (empty($tables['files'])) {
            throw new RuntimeException('No table facts available for xBRL-CSV export.');
        }

        $packageBaseName = $this->buildPackageBaseName();
        $archivePath = tempnam(sys_get_temp_dir(), 'areport_xcsv_');

        if ($archivePath === false) {
            throw new RuntimeException('Unable to allocate temporary file for xBRL-CSV export.');
        }

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create xBRL-CSV archive.');
        }

        $zip->addFromString(
            $packageBaseName . '/META-INF/reportPackage.json',
            $this->encodeJson([
                'documentInfo' => [
                    'documentType' => 'https://xbrl.org/report-package/2023',
                ],
            ])
        );

        $zip->addFromString(
            $packageBaseName . '/reports/report.json',
            $this->encodeJson([
                'documentInfo' => [
                    'documentType' => 'https://xbrl.org/2021/xbrl-csv',
                    'extends' => [
                        $this->moduleJsonUrl,
                    ],
                ],
            ])
        );

        $zip->addFromString(
            $packageBaseName . '/reports/parameters.csv',
            $this->buildCsvContent(
                ['name', 'value'],
                $this->buildParameterRows()
            )
        );

        $zip->addFromString(
            $packageBaseName . '/reports/FilingIndicators.csv',
            $this->buildCsvContent(
                ['templateID', 'reported'],
                $this->buildFilingIndicatorRows($tables['filing_indicators'])
            )
        );

        foreach ($tables['files'] as $fileName => $content) {
            $zip->addFromString($packageBaseName . '/reports/' . $fileName, $content);
        }

        $zip->close();

        return [
            'path' => $archivePath,
            'filename' => $packageBaseName . '.zip',
        ];
    }

    private function bootMetadata()
    {
        $moduleJson = preg_replace('/\.xsd$/', '.json', $this->modulePath);

        if (is_null($moduleJson) || $moduleJson === $this->modulePath) {
            throw new RuntimeException('Unable to derive module JSON metadata path from module schema path.');
        }

        $this->moduleJsonPath = $this->localTaxonomyPath($moduleJson);

        if (!is_file($this->moduleJsonPath)) {
            throw new RuntimeException('Module JSON metadata file was not found for the selected module.');
        }

        $this->moduleMetadata = $this->decodeJsonFile($this->moduleJsonPath);
        $this->moduleJsonUrl = $this->toOfficialUrl($moduleJson);
        $this->tableMetadataPaths = $this->resolveTableMetadataPaths();
    }

    private function buildTableFiles(): array
    {
        $factsByTable = [];

        foreach ($this->facts as $fact) {
            if (empty($fact['table_path'])) {
                continue;
            }

            $tableBaseName = strtolower(pathinfo(basename($fact['table_path']), PATHINFO_FILENAME));
            $factsByTable[$tableBaseName][] = $fact;
        }

        $files = [];
        $filingIndicators = [];

        foreach ($this->moduleMetadata['tables'] ?? [] as $table) {
            $templateName = $table['template'] ?? null;
            $csvFileName = $table['url'] ?? null;

            if (empty($templateName) || empty($csvFileName)) {
                continue;
            }

            if ($templateName === 'FilingIndicators' || $templateName === 'FootNotes') {
                continue;
            }

            $tableBaseName = strtolower(pathinfo($csvFileName, PATHINFO_FILENAME));

            if (empty($factsByTable[$tableBaseName])) {
                continue;
            }

            $tableMetadataPath = $this->resolveTableMetadataPath($tableBaseName, $factsByTable[$tableBaseName][0]['table_path']);
            $tableMetadata = $this->loadTableMetadata($tableMetadataPath);
            $template = $tableMetadata['tableTemplates'][$templateName] ?? null;

            if (is_null($template)) {
                $template = reset($tableMetadata['tableTemplates']);
            }

            if (is_null($template)) {
                continue;
            }

            $files[$csvFileName] = $this->renderTableCsv($template, $factsByTable[$tableBaseName]);

            $filingIndicator = $table['eba:documentation']['FilingIndicator'] ?? $templateName;
            $filingIndicators[$this->normalizeTemplateId((string) $filingIndicator)] = true;
        }

        return [
            'files' => $files,
            'filing_indicators' => $filingIndicators,
        ];
    }

    private function renderTableCsv(array $template, array $facts): string
    {
        $columns = $template['columns'] ?? [];

        if (isset($columns['datapoint']) && isset($columns['factValue'])) {
            return $this->renderDatapointTableCsv($template, $facts);
        }

        if (!empty($template['dimensions'])) {
            return $this->renderTemplateDimensionTableCsv($template, $facts);
        }

        throw new RuntimeException('Unsupported xBRL-CSV table template: datapoint mapping was not found.');
    }

    private function renderDatapointTableCsv(array $template, array $facts): string
    {
        $columns = $template['columns'] ?? [];
        $propertyOrder = array_flip(array_keys($columns['datapoint']['propertyGroups'] ?? []));
        $dynamicColumns = $this->resolveDynamicColumns($columns);
        $header = array_merge(['datapoint', 'factValue'], array_keys($dynamicColumns));
        $rows = [];

        foreach ($facts as $fact) {
            if (empty($fact['metric'])) {
                continue;
            }

            $match = $this->matchDatapoint($columns, $fact);

            if (is_null($match)) {
                continue;
            }

            $row = [
                'datapoint' => $match['datapoint'],
                'factValue' => $this->formatFactValue($fact['value']),
                '__sort_datapoint' => $propertyOrder[$match['datapoint']] ?? PHP_INT_MAX,
            ];

            foreach ($dynamicColumns as $columnName => $dimensionKey) {
                $row[$columnName] = $this->resolveDynamicColumnValue($columnName, $dimensionKey, $fact, $match);
            }

            $rows[] = $row;
        }

        usort($rows, function ($left, $right) use ($dynamicColumns) {
            foreach (array_keys($dynamicColumns) as $columnName) {
                $comparison = strcmp((string) ($left[$columnName] ?? ''), (string) ($right[$columnName] ?? ''));

                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            if (($left['__sort_datapoint'] ?? null) !== ($right['__sort_datapoint'] ?? null)) {
                return ($left['__sort_datapoint'] ?? PHP_INT_MAX) <=> ($right['__sort_datapoint'] ?? PHP_INT_MAX);
            }

            return strcmp((string) ($left['datapoint'] ?? ''), (string) ($right['datapoint'] ?? ''));
        });

        foreach ($rows as &$row) {
            unset($row['__sort_datapoint']);
        }
        unset($row);

        return $this->buildCsvContent($header, $rows);
    }

    private function renderDirectTableCsv(array $template, array $facts): string
    {
        $columns = $template['columns'] ?? [];
        $header = array_keys($columns);
        $rows = [];

        foreach ($facts as $fact) {
            if (empty($fact['column_code'])) {
                continue;
            }

            if (!array_key_exists($fact['column_code'], $columns)) {
                continue;
            }

            $groupKey = $fact['sheetcode'] . '|' . ($fact['row_code'] ?? 'r0');

            if (!array_key_exists($groupKey, $rows)) {
                $rows[$groupKey] = array_fill_keys($header, '');
                $rows[$groupKey]['__sheet'] = $fact['sheetcode'];
                $rows[$groupKey]['__row_index'] = $fact['row_index'] ?? 0;
            }

            $rows[$groupKey][$fact['column_code']] = $this->formatFactValue($fact['value']);
        }

        uasort($rows, function ($left, $right) {
            if ($left['__sheet'] === $right['__sheet']) {
                return ($left['__row_index'] <=> $right['__row_index']);
            }

            return strcmp($left['__sheet'], $right['__sheet']);
        });

        $csvRows = [];
        foreach ($rows as $row) {
            unset($row['__sheet'], $row['__row_index']);
            $csvRows[] = $row;
        }

        return $this->buildCsvContent($header, $csvRows);
    }

    private function renderTemplateDimensionTableCsv(array $template, array $facts): string
    {
        $dynamicColumns = $this->resolveTemplateDimensionColumns($template);
        $header = array_merge(['datapoint', 'factValue'], array_keys($dynamicColumns));
        $rowFacts = [];
        $rows = [];

        foreach ($facts as $fact) {
            $groupKey = $this->buildFactGroupKey($fact);
            $columnCode = $this->resolveFactColumnReference($fact);

            if (!empty($columnCode)) {
                $rowFacts[$groupKey][$columnCode] = $fact;
            }
        }

        foreach ($facts as $fact) {
            if (empty($fact['metric'])) {
                continue;
            }

            $datapoint = $this->resolveFactDatapoint($fact);

            if ($datapoint === null) {
                continue;
            }

            $groupKey = $this->buildFactGroupKey($fact);
            $row = [
                'datapoint' => $datapoint,
                'factValue' => $this->formatFactValue($fact['value']),
            ];

            foreach ($dynamicColumns as $columnName => $metadata) {
                $sourceFact = $rowFacts[$groupKey][$metadata['source_column']] ?? null;

                if (is_array($sourceFact) && array_key_exists('value', $sourceFact)) {
                    $row[$columnName] = $this->formatFactValue($sourceFact['value']);
                    continue;
                }

                $row[$columnName] = $fact['context'][$metadata['dimension_key']] ?? '';
            }

            $rows[] = $row;
        }

        usort($rows, function ($left, $right) use ($dynamicColumns) {
            foreach (array_keys($dynamicColumns) as $columnName) {
                $comparison = strcmp((string) ($left[$columnName] ?? ''), (string) ($right[$columnName] ?? ''));

                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return strcmp((string) ($left['datapoint'] ?? ''), (string) ($right['datapoint'] ?? ''));
        });

        return $this->buildCsvContent($header, $rows);
    }

    private function matchDatapoint(array $columns, array $fact): ?array
    {
        $propertyGroups = $columns['datapoint']['propertyGroups'] ?? [];
        $dynamicColumns = $this->resolveDynamicColumns($columns);
        $factDimensions = $this->normalizeFactDimensions($fact);

        if (!empty($fact['meta']['datapoint']) && isset($propertyGroups[$fact['meta']['datapoint']])) {
            $group = $propertyGroups[$fact['meta']['datapoint']];

            return [
                'datapoint' => $fact['meta']['datapoint'],
                'group' => $group,
                'dynamic_columns' => $dynamicColumns,
            ];
        }

        foreach (['fact_variable_id', 'fact_variable_version_id'] as $metaKey) {
            if (empty($fact['meta'][$metaKey])) {
                continue;
            }

            $candidate = 'dp' . $fact['meta'][$metaKey];

            if (!isset($propertyGroups[$candidate])) {
                continue;
            }

            return [
                'datapoint' => $candidate,
                'group' => $propertyGroups[$candidate],
                'dynamic_columns' => $dynamicColumns,
            ];
        }

        foreach ($propertyGroups as $datapoint => $group) {
            $dimensions = $group['dimensions'] ?? [];
            $staticDimensions = [];
            $groupDynamicColumns = [];

            foreach ($dimensions as $dimensionKey => $value) {
                if ($this->isDynamicReference($value)) {
                    $columnName = substr($value, 1);

                    if (array_key_exists($columnName, $columns)) {
                        $groupDynamicColumns[$columnName] = $dimensionKey;
                    }

                    continue;
                }

                $staticDimensions[$dimensionKey] = $value;
            }

            if (!$this->matchesStaticDimensions($factDimensions, $staticDimensions)) {
                continue;
            }

            return [
                'datapoint' => $datapoint,
                'group' => $group,
                'dynamic_columns' => array_merge($dynamicColumns, $groupDynamicColumns),
            ];
        }

        return null;
    }

    private function resolveFactDatapoint(array $fact): ?string
    {
        if (!empty($fact['meta']['datapoint'])) {
            return (string) $fact['meta']['datapoint'];
        }

        foreach (['fact_variable_id', 'fact_variable_version_id'] as $metaKey) {
            if (empty($fact['meta'][$metaKey])) {
                continue;
            }

            return 'dp' . $fact['meta'][$metaKey];
        }

        return null;
    }

    private function matchesStaticDimensions(array $factDimensions, array $staticDimensions): bool
    {
        foreach ($staticDimensions as $dimensionKey => $value) {
            if (!array_key_exists($dimensionKey, $factDimensions)) {
                return false;
            }

            if ((string) $factDimensions[$dimensionKey] !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    private function normalizeFactDimensions(array $fact): array
    {
        $dimensions = $fact['context'] ?? [];

        if (!is_array($dimensions)) {
            $dimensions = [];
        }

        if (!empty($fact['metric'])) {
            $dimensions['concept'] = $fact['metric'];
        }

        return $dimensions;
    }

    private function resolveDynamicColumns(array $columns): array
    {
        $dynamicColumns = [];

        foreach (($columns['factValue']['dimensions'] ?? []) as $dimensionKey => $value) {
            if ($this->isDynamicReference($value)) {
                $columnName = substr($value, 1);

                if (array_key_exists($columnName, $columns)) {
                    $dynamicColumns[$columnName] = $dimensionKey;
                }
            }
        }

        foreach (($columns['datapoint']['propertyGroups'] ?? []) as $group) {
            foreach (($group['dimensions'] ?? []) as $dimensionKey => $value) {
                if ($this->isDynamicReference($value)) {
                    $columnName = substr($value, 1);

                    if (array_key_exists($columnName, $columns)) {
                        $dynamicColumns[$columnName] = $dimensionKey;
                    }
                }
            }
        }

        return $dynamicColumns;
    }

    private function resolveTemplateDimensionColumns(array $template): array
    {
        $dimensionsBySource = [];

        foreach (($template['dimensions'] ?? []) as $dimensionKey => $value) {
            if (!$this->isDynamicReference($value)) {
                continue;
            }

            $sourceColumn = substr($value, 1);
            $dimensionsBySource[$sourceColumn] = [
                'dimension_key' => $dimensionKey,
                'source_column' => $sourceColumn,
                'column_name' => $this->normalizeDimensionColumnName($dimensionKey),
            ];
        }

        $columns = [];

        foreach (($template['tc:keys']['primary']['fields'] ?? []) as $sourceColumn) {
            if (!isset($dimensionsBySource[$sourceColumn])) {
                continue;
            }

            $metadata = $dimensionsBySource[$sourceColumn];
            $columns[$metadata['column_name']] = [
                'dimension_key' => $metadata['dimension_key'],
                'source_column' => $metadata['source_column'],
            ];
            unset($dimensionsBySource[$sourceColumn]);
        }

        foreach ($dimensionsBySource as $metadata) {
            $columns[$metadata['column_name']] = [
                'dimension_key' => $metadata['dimension_key'],
                'source_column' => $metadata['source_column'],
            ];
        }

        return $columns;
    }

    private function resolveDynamicColumnValue($columnName, $dimensionKey, array $fact, array $match): string
    {
        $factDimensions = $this->normalizeFactDimensions($fact);

        if (array_key_exists($dimensionKey, $factDimensions)) {
            return (string) $factDimensions[$dimensionKey];
        }

        if ($columnName === 'unit') {
            return $this->resolveUnitValue($fact, $match['group'] ?? []);
        }

        return '';
    }

    private function buildFactGroupKey(array $fact): string
    {
        return ($fact['sheetcode'] ?? '000') . '|' . ($fact['row_code'] ?? 'r0');
    }

    private function resolveFactColumnReference(array $fact): ?string
    {
        if (!empty($fact['column_code'])) {
            return (string) $fact['column_code'];
        }

        if (empty($fact['meta']['header_code'])) {
            return null;
        }

        $headerCode = (string) $fact['meta']['header_code'];
        $headerCode = preg_replace('/^c/i', '', $headerCode);

        if ($headerCode === null || $headerCode === '') {
            return null;
        }

        return 'c' . str_pad($headerCode, 4, '0', STR_PAD_LEFT);
    }

    private function resolveUnitValue(array $fact, array $group): string
    {
        $documentation = $group['eba:documentation'] ?? [];
        $type = $documentation['type'] ?? null;

        if ($type === 'm') {
            return $this->buildBaseCurrency();
        }

        return '';
    }

    private function buildParameterRows(): array
    {
        $defaults = $this->moduleMetadata['parameters'] ?? [];
        $requiredParameters = $this->collectReferencedParameters();
        $orderedNames = [
            'entityID',
            'refPeriod',
            'decimalsInteger',
            'baseCurrency',
            'decimalsMonetary',
            'decimalsPercentage',
            'decimalsDecimal',
            'baseLanguage',
        ];
        $rows = [];

        foreach ($orderedNames as $name) {
            if (!isset($requiredParameters[$name])) {
                continue;
            }

            if (array_key_exists($name, $defaults)) {
                continue;
            }

            $value = $this->resolveParameterValue($name);

            if ($value === null) {
                continue;
            }

            $rows[] = ['name' => $name, 'value' => $value];
        }

        return $rows;
    }

    private function buildFilingIndicatorRows(array $filingIndicators): array
    {
        $rows = [];

        ksort($filingIndicators);

        foreach ($filingIndicators as $templateId => $reported) {
            $rows[] = [
                'templateID' => $templateId,
                'reported' => $reported ? 'true' : 'false',
            ];
        }

        return $rows;
    }

    private function buildCsvContent(array $header, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Unable to open temporary CSV stream.');
        }

        fputcsv($stream, $header);

        foreach ($rows as $row) {
            $line = [];
            foreach ($header as $column) {
                $line[] = $row[$column] ?? '';
            }
            fputcsv($stream, $line);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content === false ? '' : $content;
    }

    private function resolveTableMetadataPath($tableBaseName, $tablePath): string
    {
        if (array_key_exists($tableBaseName, $this->tableMetadataPaths)) {
            return $this->tableMetadataPaths[$tableBaseName];
        }

        $tableJson = preg_replace('/\.xsd$/', '.json', $tablePath);

        if (is_null($tableJson) || $tableJson === $tablePath) {
            throw new RuntimeException('Unable to resolve table JSON metadata file.');
        }

        $tableJsonPath = $this->localTaxonomyPath($tableJson);

        if (!is_file($tableJsonPath)) {
            throw new RuntimeException('Table JSON metadata file was not found for the selected table.');
        }

        return $tableJsonPath;
    }

    private function resolveTableMetadataPaths(): array
    {
        $paths = [];
        $extends = $this->moduleMetadata['documentInfo']['extends'] ?? [];

        foreach ($extends as $extendPath) {
            if (!is_string($extendPath) || substr($extendPath, -5) !== '.json') {
                continue;
            }

            if (strpos($extendPath, 'FilingIndicators.json') !== false || strpos($extendPath, 'FootNotes.json') !== false) {
                continue;
            }

            $absolutePath = $this->resolveRelativePath(dirname($this->moduleJsonPath), $extendPath);

            if (!is_file($absolutePath)) {
                continue;
            }

            $paths[strtolower(pathinfo($absolutePath, PATHINFO_FILENAME))] = $absolutePath;
        }

        return $paths;
    }

    private function loadTableMetadata(string $path): array
    {
        if (!array_key_exists($path, $this->tableMetadataCache)) {
            $this->tableMetadataCache[$path] = $this->decodeJsonFile($path);
        }

        return $this->tableMetadataCache[$path];
    }

    private function resolveRelativePath($baseDir, $relativePath): string
    {
        $relativePath = str_replace('\\', '/', $relativePath);

        if (strpos($relativePath, 'http://') === 0 || strpos($relativePath, 'https://') === 0) {
            return $relativePath;
        }

        $candidate = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $resolved = realpath($candidate);

        return $resolved ?: $candidate;
    }

    private function decodeJsonFile($path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('Unable to read JSON metadata file: ' . $path);
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Unable to decode JSON metadata file: ' . $path);
        }

        return $decoded;
    }

    private function encodeJson(array $data): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Unable to encode JSON export metadata.');
        }

        return $json . PHP_EOL;
    }

    private function localTaxonomyPath($relativePath): string
    {
        $base = rtrim(Config::publicDir(), DIRECTORY_SEPARATOR);
        $folder = trim((string) $this->moduleDir, DIRECTORY_SEPARATOR);
        $relative = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);

        return $base . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $relative;
    }

    private function toOfficialUrl($relativePath): string
    {
        $path = str_replace('\\', '/', $relativePath);
        $position = strpos($path, 'www.');

        if ($position !== false) {
            return 'http://' . substr($path, $position);
        }

        return $path;
    }

    private function buildPackageBaseName(): string
    {
        $entity = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $this->entityId);
        $module = strtoupper(pathinfo($this->modulePath, PATHINFO_FILENAME));
        $timestamp = date('YmdHis');

        return $entity . '_' . $module . '_' . $this->period . '_' . $timestamp;
    }

    private function buildEntityIdentifier(): string
    {
        if (strpos($this->entityId, ':') !== false) {
            return $this->entityId;
        }

        return 'rs:' . $this->entityId;
    }

    private function buildBaseCurrency(): string
    {
        return 'iso4217:' . Config::$monetaryItem;
    }

    private function normalizeTemplateId($templateName): string
    {
        return str_replace('-', '.', $templateName);
    }

    private function normalizeDimensionColumnName(string $dimensionKey): string
    {
        $position = strrpos($dimensionKey, ':');

        if ($position === false) {
            return $dimensionKey;
        }

        return substr($dimensionKey, $position + 1);
    }

    private function collectReferencedParameters(): array
    {
        $parameters = [];

        $this->collectStringParameterReferences($this->moduleMetadata, $parameters);

        foreach ($this->tableMetadataPaths as $path) {
            $this->collectStringParameterReferences($this->loadTableMetadata($path), $parameters);
        }

        return $parameters;
    }

    private function collectStringParameterReferences($value, array &$parameters): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->collectStringParameterReferences($item, $parameters);
            }

            return;
        }

        if (!is_string($value)) {
            return;
        }

        if (!preg_match('/^\$([A-Za-z][A-Za-z0-9_]*)/', $value, $matches)) {
            return;
        }

        $parameters[$matches[1]] = true;
    }

    private function resolveParameterValue(string $name): ?string
    {
        switch ($name) {
            case 'entityID':
                return $this->buildEntityIdentifier();
            case 'refPeriod':
                return $this->period;
            case 'baseLanguage':
                return 'en';
            case 'baseCurrency':
                return $this->buildBaseCurrency();
            case 'decimalsMonetary':
                return '-3';
            case 'decimalsPercentage':
                return '4';
            case 'decimalsInteger':
                return '0';
            case 'decimalsDecimal':
                return '2';
            default:
                return null;
        }
    }

    private function formatFactValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function isDynamicReference($value): bool
    {
        return is_string($value) && strpos($value, '$') === 0;
    }
}
