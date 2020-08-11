<?php


namespace AReportDpmXBRL\Render\RenderTrait;


use AReportDpmXBRL\Config\Config;
use Exception;


trait RTrait
{

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
     * @return string|null
     */
    public function tableName(): ?string
    {
        return $this->searchLabel($this->tableLabelName(), 'http://www.xbrl.org/2008/role/label');
    }

    /**
     * @return string|null
     */
    public function tableVerboseName(): ?string
    {

        return $this->searchLabel($this->tableLabelName(), 'http://www.xbrl.org/2008/role/verboseLabel');

    }


}
