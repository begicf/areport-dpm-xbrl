<?php


namespace AReportDpmXBRL\Library;

/**
 * Class ArrayManipulation
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class ArrayManipulation
{

    /**
     * Search hyperlink
     * @param $arr
     * @param $value
     * @param string $field
     * @param string $needle
     * @return array|null
     */
    public static function searchHref($arr, $value, $field = 'href', $needle = '#'): ?array
    {
        if (!empty($arr)):
            $found = array();

            foreach ($arr as $key => $row):

                if (isset($row[$field])):

                    $str = Format::getAfterSpecChar($row[$field], $needle);

                    if ($str === $value):
                        $found[$key] = $row;
                    endif;


                endif;

            endforeach;
            return $found;
        endif;
        return null;
    }
}
