<?php

$opts = getopt('f:');

$reader = new \XMLReader();
$reader->open($opts['f']);

$writer = new \XMLWriter();
$writer->openMemory();

const NS_DOCKBOOK = 'http://docbook.org/ns/docbook';
const NS_XLINK = "http://www.w3.org/1999/xlink";
const NS_EZXHTML = "http://ez.no/xmlns/ezpublish/docbook/xhtml";

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
    /** @var $reader \XMLReader */
    return (object) [
        'name' => $reader->name,
        'namespace' => $reader->namespaceURI,
        'value' => $reader->value,
    ];
}

function skipTillEnd($reader)
{
    /** @var $reader \XMLReader */

    if ($reader->isEmptyElement) {
        return;
    }
    $startName = $reader->name;
    $startDepth = $reader->depth;
    while ($reader->read() && $reader->name !== $startName && $reader->depth !== $startDepth) {
        //fprintf(STDERR, "skip %s" . PHP_EOL, $reader->name);
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
                case '':
                    $tag = 'em';
                    break;
                default:
                    $tag = "unknown-{$el->getAttribute('role')}";
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
//        var_err_dump($xml);
//
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('x', NS_XLINK);
        $xp->registerNamespace('h', NS_EZXHTML);
        $xp->registerNamespace('d', NS_DOCKBOOK);

        if ($el->nodeType == \XMLReader::ELEMENT) {
            switch ($el->getAttributeNs('class', NS_EZXHTML)) {
                case 'ez-embed-type-image':
                    $writer->startElement('img');
                    $writer->writeAttribute(
                        'size',
                        $xp->query('/d:ezembed/d:ezconfig/d:ezvalue[@key="size"]')->item(0)->nodeValue
                    );
                    $writer->writeAttribute(
                        'src',
                        $el->getAttributeNs('href', NS_XLINK)
                    );
                    break;
                default:
                    $writer->startElement('embed');
                    $writer->writeAttribute(
                        'src',
                        $el->getAttributeNs('href', NS_XLINK)
                    );
            }
            skipTillEnd($el);
            $writer->endElement();
            return 'pop';
        }
    },
    fqn(NS_DOCKBOOK, 'itemizedlist') => function ($el, $stack) use ($writer) {
        /** @var $el \XMLReader */
        /** @var $writer \XMLWriter */

        switch ($el->nodeType) {
            case  \XMLReader::ELEMENT:
                $writer->startElement('ul');
                break;
            case  \XMLReader::END_ELEMENT:
                $writer->endElement();
                break;
        }
    },
    fqn(NS_DOCKBOOK, 'listitem') => function ($el, $stack) use ($writer) {
        /** @var $el \XMLReader */
        /** @var $writer \XMLWriter */

        switch ($el->nodeType) {
            case  \XMLReader::ELEMENT:
                $writer->startElement('li');
                break;
            case  \XMLReader::END_ELEMENT:
                $writer->endElement();
                break;
        }
    },
    fqn(NS_DOCKBOOK, 'superscript') => function ($el, $stack) use ($writer) {
        /** @var $el \XMLReader */
        /** @var $writer \XMLWriter */

        switch ($el->nodeType) {
            case  \XMLReader::ELEMENT:
                $writer->startElement('sup');
                break;
            case  \XMLReader::END_ELEMENT:
                $writer->endElement();
                break;
        }
    },
    fqn(NS_DOCKBOOK, 'link') => function ($el, $stack) use ($writer) {
        /** @var $el \XMLReader */
        /** @var $writer \XMLWriter */

        switch ($el->nodeType) {
            case  \XMLReader::ELEMENT:
                $writer->startElement('a');
                $writer->writeAttribute('title', $el->getAttributeNs('title', NS_XLINK));
                $writer->writeAttribute('href', $el->getAttributeNs('href', NS_XLINK));
                break;
            case  \XMLReader::END_ELEMENT:
                $writer->endElement();
                break;
        }
    },
    fqn(NS_DOCKBOOK, 'title') => function ($el, $stack) use ($writer) {
        /** @var $el \XMLReader */
        /** @var $writer \XMLWriter */

        switch ($el->nodeType) {
            case  \XMLReader::ELEMENT:
                switch ($el->getAttributeNs('level', NS_EZXHTML)) {
                    case 1:
                        $writer->startElement('h1');
                        break;
                    case 2:
                        $writer->startElement('h2');
                        break;
                    case 3:
                        $writer->startElement('h3');
                        break;
                    case 4:
                        $writer->startElement('h4');
                        break;
                    case 5:
                        $writer->startElement('h5');
                        break;
                }
                break;
            case  \XMLReader::END_ELEMENT:
                $writer->endElement();
                break;
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

    false && fputs(STDERR, str_repeat(" ", $reader->depth) . join(' $ ', [
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
    } else {
        fprintf(STDERR, "!!! unknown tag [%s:%s]", $reader->namespaceURI, $reader->name);
    }

    if ($popAtTheEnd) {
        array_pop($stack);
    }
}

echo $writer->flush();
