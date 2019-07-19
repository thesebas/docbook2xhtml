<?php

$opts = getopt('f:');

$reader = new \XMLReader();
$reader->open($opts['f']);

$writer = new \XMLWriter();
$writer->openMemory();

const NS_DOCKBOOK = 'http://docbook.org/ns/docbook';
const NS_XLINK = "http://www.w3.org/1999/xlink";
const NS_EZXHTML = "http://ez.no/xmlns/ezpublish/docbook/xhtml";
function fqn($ns, $name)
{
    return "$ns:$name";
}

function snapshotReader($reader)
{
    /** @var $reader \XMLReader */
    return (object) [
        'name' => $reader->name,
        'namespace' => $reader->namespaceURI,
        'value' => $reader->value,
    ];
}

function readTillClose($reader)
{
    /** @var $reader \XMLReader */

    if ($reader->isEmptyElement) {
        return;
    }
    while ($reader->read() && $reader->name !== 'ezembed') {
        fprintf(STDERR, "skip %s" . PHP_EOL, $reader->name);
    }
}
$callbacks = [
    fqn('', '#text') => function ($el) use ($writer) {
        /** @var $el \XMLReader */
        $writer->writeRaw($el->value);
    },
    fqn(NS_DOCKBOOK, 'section') => function ($el) use ($writer) {
        if ($el->nodeType == \XMLReader::ELEMENT) {
            $writer->startElement('div');
        } else {
            $writer->endElement();
        }
    },
    fqn(NS_DOCKBOOK, 'para') => function ($el) use ($writer) {
        if ($el->nodeType == \XMLReader::ELEMENT) {
            $writer->startElement('p');
        } else {
            $writer->endElement();
        }
    },
    fqn(NS_DOCKBOOK, 'emphasis') => function ($el) use ($writer) {
        if ($el->nodeType == \XMLReader::ELEMENT) {
            /** @var $el \XMLReader */
            switch ($el->getAttribute('role')) {
                case 'strong':
                    $tag = 'strong';
                    break;
                case 'italic':
                    $tag = 'i';
                    break;
                default:
                    $tag = 'unknown';
            }
            $writer->startElement($tag);
        } else {
            $writer->endElement();
        }
    },

    fqn(NS_DOCKBOOK, 'ezembed') => function ($el, $stack) use ($writer) {
        /** @var $el \XMLReader */
        $doc = new \DOMDocument();
        $xml = $el->readOuterXml();
        $doc->loadXML($xml);

        if ($el->nodeType == \XMLReader::ELEMENT) {
            $writer->startElement('embed');
            readTillClose($el);
            $writer->endElement();
            return 'pop';
        }
    },
];

$stack = [];
$isOpen = null;
while ($reader->read()) {
    $popAtTheEnd = false;
    switch ($reader->nodeType) {
        case \XMLReader::TEXT:
        case \XMLReader::SIGNIFICANT_WHITESPACE:
            $popAtTheEnd = true;
        case \XMLReader::ELEMENT:
            $stack[] = snapshotReader($reader);
            break;
        case \XMLReader::END_ELEMENT:
            array_pop($stack);
            break;
    }

    fputs(STDERR, str_repeat(" ", $reader->depth) . join(' $ ', [
            $reader->nodeType,
            $reader->namespaceURI,
            $reader->name,
            $reader->depth,
            " | " . implode(" > ", array_map(function ($el) {
                return $el->name;
            }, $stack)),
        ]) . PHP_EOL);

    if (isset($callbacks["{$reader->namespaceURI}:{$reader->name}"])) {
        $res = call_user_func(
            $callbacks["{$reader->namespaceURI}:{$reader->name}"],
            $reader,
            $stack
        );
        if ($res === false) {
            break;
        }
        if ($res === 'pop') {
            array_pop($stack);
        }
    }

    if ($popAtTheEnd) {
        array_pop($stack);
    }
}

echo $writer->flush();