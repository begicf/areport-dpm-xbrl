<?php


namespace AReportDpmXBRL\Library;


use AReportDpmXBRL\Config\Config;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveCallbackFilterIterator;

/**
 * Class Directory
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class Directory
{

    /**
     * @param $path
     * @param array $string
     * @param bool $return_first_content
     * @param int $max_depth
     * @param array $ext
     * @return array|null
     */
    public static function getPath($path, $pattern = [], $return_first_content = FALSE, $max_depth = 10, $ext = ['xsd'])
    {

        $content = NULL;
        $dir = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

        $iterator->setMaxDepth($max_depth);

        foreach ($iterator as $file) :

            $content = $file->getPathname();

            foreach ($pattern as $key => $str):

                if (strpos($content, $str) !== false) :

                    if (in_array($file->getExtension(), $ext)):

                        if ($return_first_content === TRUE):
                            return $content;
                        else:
                            $dir[$key][] = $content;
                        endif;

                    endif;

                endif;

            endforeach;

        endforeach;

        return $dir;
    }

    /**
     * Directory search recursive globe method
     * @param $pattern
     * @return string|null
     */
    public static function searchFileGlob($pattern, $flags = 0): ?array
    {

        $files = glob($pattern, $flags);

        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {

            $files = array_merge($files, self::searchFileGlob($dir . '/' . basename($pattern), $flags));
        }
        return $files;

    }

    /**
     * Directory Search Recursive Iterator
     * @param $directory
     * @param $pattern
     * @return string|null
     */
    public static function searchFile($directory, $pattern, $maxDepth = 6): ?array
    {


        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        $iterator->setMaxDepth($maxDepth);
        foreach ($iterator as $file) {

            if (strpos($file, $pattern) !== false) {
                $files[] = $file;
            }
        }

        return $files;

    }


    /**
     * Directory search recursive Iterator exclude some directory
     * @param $directory
     * @param $pattern
     * @param int $maxDepth
     * @return string|null
     */
    public static function searchFileExclude($directory, $pattern, $maxDepth = 6, $exclude = ['www.xbrl.org', 'www.eurofiling.info', 'ext', 'dict', 'func', 'tab', 'val']): ?array
    {

        $files = [];

        $filter = function ($file, $key, $iterator) use ($exclude) {
            if ($iterator->hasChildren() && !in_array($file->getFilename(), $exclude)) {
                return true;
            }
            return $file->isFile();
        };

        $innerIterator = new RecursiveDirectoryIterator(
            $directory, RecursiveDirectoryIterator::SKIP_DOTS
        );
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator($innerIterator, $filter),RecursiveIteratorIterator::SELF_FIRST
        );
        $iterator->setMaxDepth($maxDepth);


        foreach ($iterator as $pathname => $file) {

            if (strpos($file->getFilename(), $pattern) !== false) {
                $files[] = $file;

            }
        }
        return $files;
    }


    /**
     * @param $file_path
     * @return string
     */
    public static function getRootName($file_path): string
    {

        return Format::getBeforeSpecChar(substr($file_path, strlen(Config::publicDir())), '/');

    }

    /**
     * @param $file_path
     * @return string
     */

    public static function getOwnerAbsolutePath($file_path): string
    {

        return Config::publicDir() . self::getRootName($file_path) . DIRECTORY_SEPARATOR . Config::$owner;
    }

    /**
     * @param $file_path
     * @return string
     */
    public static function getOwnerUrl($file_path): ?string
    {
        return self::getStringBetween($file_path, self::getRootName($file_path) . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR);

    }

    /**
     * @param $file_path
     * @return string
     */
    public static function getLastPathDirName($file_path): string
    {
        $_info = pathinfo($file_path);
        $_dir = explode(DIRECTORY_SEPARATOR, $_info['dirname']);
        return end($_dir);

    }

    /**
     * @param $string
     * @param $start
     * @param $end
     * @return string
     */
    public static function getStringBetween($string, $start, $end): string
    {

        $pos = stripos($string, $start);
        $str = substr($string, $pos);
        $str_two = substr($str, strlen($start));
        $second_pos = stripos($str_two, $end);
        $str_three = substr($str_two, 0, $second_pos);
        $unit = trim($str_three);

        return $unit;


    }
}
