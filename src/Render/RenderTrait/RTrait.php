<?php


namespace AReportDpmXBRL\Render\RenderTrait;


use AReportDpmXBRL\Config\Config;
use AReportDpmXBRL\Library\Format;
use Exception;


trait RTrait
{

    use RAxis;

    public $specification;
    public $lang;
    public $additionalData = [];

    /**
     * RTrait constructor.
     * @param null $specification XBRL specification
     * @param null $lang Set lang preference
     * @param array $additional
     * @throws Exception
     *
     */
    public function __construct($specification = null, $lang = NULL, $additional = [])
    {
        if (is_null($specification)) {
            throw new Exception('Taxonomy is not defined!');
        } else {

            $this->specification = $specification;
        }

        if (is_null($lang)):

            $keys = array_keys($this->specification);

            foreach (Config::$lang as $row):

                $needle = 'lab-' . $row;
                if (in_array($needle, $keys)):
                    $lang = $needle;
                    break;
                endif;

            endforeach;

            $this->lang = $lang;
        else:

            $this->lang = $lang;

        endif;

        $this->additionalData = $additional;
    }

    public function tableNameId()
    {

        return key($this->specification['rend']['table']);
    }

    /**
     * @return string|null
     */
    public function tableLabelName(): ?string
    {

        return $tableLabelName = $this->specification['rend']['table'][$this->tableNameId()]['label'];
    }

    /**
     * @return string|null name
     */
    public function tableName(): ?string
    {
        return $this->searchLabel($this->tableLabelName(), 'http://www.xbrl.org/2008/role/label');
    }

    /**
     * @return string|null Definition
     */
    public function tableVerboseName(): ?string
    {

        return $this->searchLabel($this->tableLabelName(), 'http://www.xbrl.org/2008/role/verboseLabel');

    }

    /**
     * @return string|null External table DB code
     */
    public function getExtCode(): ?string
    {

        return $this->searchLabel($this->specification['rend']['path'] . "#" . $this->tableLabelName(), 'http://www.eba.europa.eu/xbrl/role/dpm-db-id');

    }

    /**
     * @return string|null Target Namespace
     */
    public function getTargetNamespace(): ?string
    {

        return $this->specification['targetNamespace'];
    }

    /**
     * @return string|null Filing indicator
     */
    public function getFilingIndicator(): ?string
    {

        return $this->searchLabel($this->specification['rend']['path'] . "#" . $this->tableLabelName(), 'http://www.eurofiling.info/xbrl/role/filing-indicator-code');

    }

    /**
     * @return string|null code
     */
    public function getCode(): ?string
    {
        return basename($this->getTargetNamespace());

    }


}
