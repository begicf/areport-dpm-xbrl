<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AReportDpmXBRL;

use AReportDpmXBRL\Config\Config;
use AReportDpmXBRL\Gen\Link;
use AReportDpmXBRL\Gen\TableLinkbase;
use AReportDpmXBRL\Link\DefinitionLink;
use AReportDpmXBRL\Module\Presentation;


/**
 * Class LinkBase
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class LinkBase implements \IteratorAggregate
{

    private $links;

    public function __construct($baseLinks, $assertion = null)
    {


        foreach ($baseLinks as $key => $path):


            foreach (Config::$lang as $item) {
                if ('lab-' . $item == $key):
                    $this->links[$key] = new Link($path);
                endif;

            }

            switch ($key):

                case 'lab-codes':
                    $this->links[$key] = new Link($path);
                    break;
                case 'rend':
                    $this->links[$key] = new TableLinkbase($path);
                    break;
                case 'def':
                    $this->links[$key] = new DefinitionLink($path);
                    break;
                case 'pre':
                    $this->links[$key] = new Presentation($path);
                    break;
                default:
                    if ($assertion == TRUE):
                        $this->links[$key] = new Link($path); //assertion
                    endif;
            endswitch;

        endforeach;

        return true;
    }

    /**
     * @return ArcCollection[]
     */
    public function getIterator()
    {

        return new \ArrayIterator($this->links);
    }

}
