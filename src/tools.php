<?php

namespace thesebas\docbook2html;

use XMLReader;
use XMLWriter;
const NS_DOCBOOK = 'http://docbook.org/ns/docbook';
const NS_XLINK = "http://www.w3.org/1999/xlink";
const NS_EZXHTML = "http://ez.no/xmlns/ezpublish/docbook/xhtml";
const NS_XML = "http://www.w3.org/XML/1998/namespace";

function var_err_dump($val)
{
    ob_start();
    var_dump($val);
    fputs(STDERR, ob_get_clean());
}

function fqn($ns, $name)
{
    return "$ns:$name";
}

function snapshotReader($reader)
{
    /** @var $reader XMLReader */
    return (object) [
        'name' => $reader->name,
        'namespace' => $reader->namespaceURI,
        'value' => $reader->value,
    ];
}

function skipTillEnd($reader)
{
//    global $veryVerbose;
    /** @var $reader XMLReader */

    if ($reader->isEmptyElement) {
        return;
    }
    $startName = $reader->name;
    $startDepth = $reader->depth;
    while ($reader->read() && $reader->name !== $startName && $reader->depth !== $startDepth) {
//        if ($veryVerbose) {
//            fprintf(STDERR, "skip %s" . PHP_EOL, $reader->name);
//        }
    }
}

function mapToTag($tag, $writer)
{
    return function ($el, $stack) use ($writer, $tag) {
        /** @var $el XMLReader */
        /** @var $writer XMLWriter */

        switch ($el->nodeType) {
            case  XMLReader::ELEMENT:
                $writer->startElement($tag);
                break;
            case  XMLReader::END_ELEMENT:
                $writer->endElement();
                break;
        }
    };
}

function fixAmpersand($html)
{
    return preg_replace('@&(?!amp;)@', '&amp;', $html);
}

/**
 * @param $html
 *
 * @return array[\DOMDocument, \DOMXpath]
 */
function loadHtmlToDomWithXpath($html)
{
    $doc = new \DOMDocument();
    $doc->loadHTML($html);
    $xp = new \DOMXPath($doc);
    return [$doc, $xp];
}

/**
 * @param \DOMXPath $xp
 * @param string    $query
 * @param null      $context
 * @param bool      $regNS
 *
 * @return \DOMNode|null
 */
function xpQueryOne($xp, $query, $context = null, $regNS = true)
{
    $nodes = $xp->query($query, $context, $regNS);
    if ($nodes->length > 0) {
        return $nodes->item(0);
    }
    return null;
}