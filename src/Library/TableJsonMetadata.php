<?php

namespace AReportDpmXBRL\Library;

class TableJsonMetadata
{
    private $metadata = [];
    private $template = [];
    private $loaded = false;

    public function __construct(?string $tablePath = null)
    {
        if (empty($tablePath)) {
            return;
        }

        $jsonPath = preg_replace('/\.xsd$/', '.json', $tablePath);

        if (empty($jsonPath) || !is_file($jsonPath)) {
            return;
        }

        $content = file_get_contents($jsonPath);

        if ($content === false) {
            return;
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            return;
        }

        $template = $decoded['tableTemplates'] ?? [];
        $firstTemplate = reset($template);

        if (!is_array($firstTemplate)) {
            return;
        }

        $this->metadata = $decoded;
        $this->template = $firstTemplate;
        $this->loaded = true;
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    public function matchForCell(string $columnCode, array $payload): array
    {
        if (!$this->loaded) {
            return [];
        }

        $meta = [];
        $column = $this->resolveColumnDefinition($columnCode);

        if (!empty($column['eba:documentation']) && is_array($column['eba:documentation'])) {
            $meta = array_merge($meta, $this->normalizeDocumentation($column['eba:documentation']));
        }

        $datapoint = $this->matchDatapoint($payload);

        if (!empty($datapoint)) {
            $meta = array_merge($meta, $datapoint);
        }

        return $meta;
    }

    private function resolveColumnDefinition(string $columnCode): ?array
    {
        $columns = $this->template['columns'] ?? [];

        if (isset($columns[$columnCode]) && is_array($columns[$columnCode])) {
            return $columns[$columnCode];
        }

        $normalizedColumnCode = $this->normalizeHeaderCode($columnCode);

        foreach ($columns as $key => $column) {
            if (!is_array($column)) {
                continue;
            }

            $headerCode = $column['eba:documentation']['headerCode'] ?? null;

            if ($headerCode === null) {
                continue;
            }

            if ($this->normalizeHeaderCode((string) $headerCode) === $normalizedColumnCode) {
                return $column;
            }
        }

        return null;
    }

    private function matchDatapoint(array $payload): array
    {
        $propertyGroups = $this->template['columns']['datapoint']['propertyGroups'] ?? [];

        if (empty($propertyGroups)) {
            return [];
        }

        $dimensions = $this->normalizePayloadDimensions($payload);

        foreach ($propertyGroups as $datapoint => $group) {
            $groupDimensions = $group['dimensions'] ?? [];

            if (!$this->matchesGroupDimensions($dimensions, $groupDimensions)) {
                continue;
            }

            $meta = ['datapoint' => $datapoint];

            if (!empty($group['eba:documentation']) && is_array($group['eba:documentation'])) {
                $meta = array_merge($meta, $this->normalizeDocumentation($group['eba:documentation']));
            }

            return $meta;
        }

        return [];
    }

    private function normalizePayloadDimensions(array $payload): array
    {
        if (isset($payload['metric']) && !isset($payload['concept'])) {
            $payload['concept'] = $payload['metric'];
        }

        unset($payload['metric'], $payload['__meta']);

        return $payload;
    }

    private function matchesGroupDimensions(array $payload, array $groupDimensions): bool
    {
        foreach ($groupDimensions as $dimensionKey => $value) {
            if (is_string($value) && strpos($value, '$') === 0) {
                continue;
            }

            if (!array_key_exists($dimensionKey, $payload)) {
                return false;
            }

            if ((string) $payload[$dimensionKey] !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    private function normalizeDocumentation(array $documentation): array
    {
        $map = [
            'FactVariableID' => 'fact_variable_id',
            'FactVariableVersionID' => 'fact_variable_version_id',
            'CellID' => 'cell_id',
            'CellCode' => 'cell_code',
            'KeyVariableID' => 'key_variable_id',
            'KeyVariableVID' => 'key_variable_vid',
            'PropertyID' => 'property_id',
            'headerCode' => 'header_code',
            'type' => 'value_type',
        ];

        $normalized = [];

        foreach ($map as $source => $target) {
            if (array_key_exists($source, $documentation)) {
                $normalized[$target] = $documentation[$source];
            }
        }

        return $normalized;
    }

    private function normalizeHeaderCode(string $value): string
    {
        $value = ltrim($value, 'cC');
        $value = ltrim($value, '0');

        return $value === '' ? '0' : $value;
    }
}
