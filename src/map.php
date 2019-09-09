<?php

namespace thesebas\docbook2html;

/**
 * @param $converter Converter
 *
 * @return array
 */
function map($converter)
{
    $writer = $converter->getXmlWriter();
    return [
        fqn(NS_DOCBOOK, 'section') => mapToTag('div', $writer),
        fqn(NS_DOCBOOK, 'para') => mapToTag('p', $writer),
        fqn(NS_DOCBOOK, 'informaltable') => mapToTag('table', $writer),
        fqn(NS_DOCBOOK, 'tbody') => mapToTag('tbody', $writer),
        fqn(NS_DOCBOOK, 'tr') => mapToTag('tr', $writer),
        fqn(NS_DOCBOOK, 'td') => mapToTag('td', $writer),
        fqn(NS_DOCBOOK, 'itemizedlist') => mapToTag('ul', $writer),
        fqn(NS_DOCBOOK, 'orderedlist') => mapToTag('ol', $writer),
        fqn(NS_DOCBOOK, 'blockquote') => mapToTag('blockquote', $writer),
        fqn(NS_DOCBOOK, 'listitem') => mapToTag('li', $writer),
        fqn(NS_DOCBOOK, 'superscript') => mapToTag('sup', $writer),
        fqn(NS_DOCBOOK, 'subscript') => mapToTag('sub', $writer),
        fqn('', '#text') => function ($el, $stack) use ($writer) {
            /** @var $el \XMLReader */
            $prevStack = $stack[count($stack) - 2] ?? false;
            if ($prevStack && $prevStack->name == 'literallayout' && $prevStack->namespace == NS_DOCBOOK) {
                $writer->writeRaw(nl2br($el->value));
            } else {
                $writer->writeRaw($el->value);
            }
        },
        fqn(NS_DOCBOOK, 'emphasis') => function ($el) use ($writer, $converter) {
            if ($el->nodeType == \XMLReader::ELEMENT) {
                /** @var $el \XMLReader */
                $role = $el->getAttribute('role');
                switch ($role) {
                    case 'strong':
                        $tag = 'strong';
                        break;
                    case 'italic':
                        $tag = 'i';
                        break;
                    case 'underlined':
                        $tag = 'u';
                        break;
                    case '':
                        $tag = 'em';
                        break;
                    default:
                        $converter->log("unknown emphasis role %s" . PHP_EOL, $role);
                        $tag = "unknown-{$role}";
                }
                $writer->startElement($tag);
            } else {
                $writer->endElement();
            }
        },
        fqn(NS_DOCBOOK, 'ezembed') => function ($el, $stack) use ($writer, $converter) {
            /** @var $el \XMLReader */
            $doc = new \DOMDocument();
            $xml = $el->readOuterXml();
            $doc->loadXML($xml);

            $xp = new \DOMXPath($doc);
            $xp->registerNamespace('x', NS_XLINK);
            $xp->registerNamespace('h', NS_EZXHTML);
            $xp->registerNamespace('d', NS_DOCBOOK);

            if ($el->nodeType == \XMLReader::ELEMENT) {
                $class = $el->getAttributeNs('class', NS_EZXHTML);
                switch ($class) {
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
                    case 'ez-embed-type-content':
                        $writer->startElement('embed');
                        $writer->writeAttribute(
                            'size',
                            $xp->query('/d:ezembed/d:ezconfig/d:ezvalue[@key="size"]')->item(0)->nodeValue
                        );
                        $writer->writeAttribute(
                            'src',
                            $el->getAttributeNs('href', NS_XLINK)
                        );
                        break;
                    case '':
                        $writer->startElement('embed');
                        $writer->writeAttribute(
                            'src',
                            $el->getAttributeNs('href', NS_XLINK)
                        );
                        break;
                    default:
                        $converter->log("unknown ezembed class [%s]" . PHP_EOL, $class);

                        $writer->startElement('embed');
                        $writer->writeAttribute(
                            'src',
                            $el->getAttributeNs('href', NS_XLINK)
                        );
                        $writer->writeAttribute('class', "unknown-{$class}");
                }
                skipTillEnd($el);
                $writer->endElement();
                return 'pop';
            }
        },
        fqn(NS_DOCBOOK, 'link') => function ($el, $stack) use ($writer) {
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
        fqn(NS_DOCBOOK, 'anchor') => function ($el, $stack) use ($writer) {
            /** @var $el \XMLReader */
            /** @var $writer \XMLWriter */

            switch ($el->nodeType) {
                case  \XMLReader::ELEMENT:
                    $writer->startElement('a');
                    $writer->writeAttribute('id', $el->getAttributeNs('id', NS_XML));
                    if ($el->isEmptyElement) {
                        $writer->fullEndElement();
                    }
                    break;
                case  \XMLReader::END_ELEMENT:
                    $writer->endElement();
                    break;
            }
        },
        fqn(NS_DOCBOOK, 'title') => function ($el, $stack) use ($writer) {
            /** @var $el \XMLReader */
            /** @var $writer \XMLWriter */

            switch ($el->nodeType) {
                case  \XMLReader::ELEMENT:
                    $level = $el->getAttributeNs('level', NS_EZXHTML);
                    $writer->startElement("h{$level}");
                    break;
                case  \XMLReader::END_ELEMENT:
                    $writer->endElement();
                    break;
            }
        },
        fqn(NS_DOCBOOK, 'literallayout') => function () {
            // ignore this tag, has no meaning
        },
        fqn(NS_DOCBOOK, 'eztemplate') => function ($el, $stack) use ($writer, $converter) {
            /** @var $el \XMLReader */

            $templateName = $el->getAttribute('name');

            /** @var $el \XMLReader */
            $doc = new \DOMDocument();
            $xml = $el->readOuterXml();

            $doc->loadXML($xml);

            $xp = new \DOMXPath($doc);
            $xp->registerNamespace('x', NS_XLINK);
            $xp->registerNamespace('h', NS_EZXHTML);
            $xp->registerNamespace('d', NS_DOCBOOK);

            if ($el->nodeType == \XMLReader::ELEMENT) {
                switch ($templateName) {
                    case 'facebook':
                        $val = $xp->query('/d:eztemplate/d:ezconfig/d:ezvalue[@key="embed"]');
                        $embedHtml = html_entity_decode($val->item(0)->nodeValue);
                        $embedHtml = fixAmpersand($embedHtml);
                        list($idoc, $ixp) = loadHtmlToDomWithXpath($embedHtml);
                        /** @var $ixp \DOMXPath */
                        if ($fburl = xpQueryOne($ixp, '//div[@class="fb-post"]/@data-href')) {
                            $fburl = $fburl->nodeValue;
                            $writer->writeRaw($fburl);
                        };
                        if ($fburl = xpQueryOne($ixp, '//iframe[contains(@src, "facebook.com")]/@src')) {
                            $fburl = $fburl->nodeValue;
                            $components = parse_url($fburl);
                            parse_str($components['query'], $query);
                            $writer->writeRaw($query['href']);
                        };

                        skipTillEnd($el);
                        break;
                    case 'twitter':
                        $val = $xp->query('/d:eztemplate/d:ezconfig/d:ezvalue[@key="tweet_url"]');
                        $embedHtml = html_entity_decode($val->item(0)->nodeValue);
                        $embedHtml = fixAmpersand($embedHtml);
                        list($idoc, $ixp) = loadHtmlToDomWithXpath($embedHtml);

                        if ($node = xpQueryOne($ixp, '//a[contains(@href, "status")]/@href')) {
                            $writer->writeRaw($node->nodeValue);
                        } elseif (is_numeric($embedHtml)) {
                            $writer->writeRaw("external://twitter?id={$embedHtml}");
                        }

                        skipTillEnd($el);
                        break;
                    case 'instagram':
                        $val = $xp->query('/d:eztemplate/d:ezconfig/d:ezvalue[@key="url"]');
                        $instaUrl = html_entity_decode($val->item(0)->nodeValue);
                        $writer->writeRaw($instaUrl);
                        skipTillEnd($el);
                        break;
                    case 'youtube':
                        $val = $xp->query('/d:eztemplate/d:ezconfig/d:ezvalue[@key="video"]');
                        $embedHtml = html_entity_decode($val->item(0)->nodeValue);
                        $embedHtml = fixAmpersand($embedHtml);
                        list($idoc, $ixp) = loadHtmlToDomWithXpath($embedHtml);
                        if ($node = xpQueryOne($ixp, '//iframe[contains(@src, "embed")]/@src')) {
                            $yturl = $node->nodeValue;
                            $writer->writeRaw($yturl);
                        } elseif (isValidUrl($embedHtml)) {
                            $writer->writeRaw($embedHtml);
                        }
                        skipTillEnd($el);
                        break;
                    case 'pinterest':
                        $val = $xp->query('/d:eztemplate/d:ezconfig/d:ezvalue[@key="embed"]');
                        $embedHtml = html_entity_decode($val->item(0)->nodeValue);
                        $embedHtml = fixAmpersand($embedHtml);
                        list($idoc, $ixp) = loadHtmlToDomWithXpath($embedHtml);
                        /** @var $ixp \DOMXPath */
                        if ($node = xpQueryOne($ixp, '//a[@data-pin-do="embedPin" or @data-pin-do="embedBoard"]/@href')) {
                            $writer->writeRaw($node->nodeValue);
                        } elseif (isValidUrl($embedHtml)) {
                            $writer->writeRaw($embedHtml);
                        }

                        skipTillEnd($el);
                        break;
                    case 'tracdelight':
                        $val = $xp->query('/d:eztemplate/d:ezconfig/d:ezvalue[@key="widgetId"]');
                        $embedHtml = html_entity_decode($val->item(0)->nodeValue);
                        $writer->writeRaw($embedHtml);
                        skipTillEnd($el);
                        break;
                    case 'opinary':
                        $val = $xp->query('/d:eztemplate/d:ezconfig/d:ezvalue[@key="url"]');
                        $embedHtml = html_entity_decode($val->item(0)->nodeValue);
                        $writer->writeRaw($embedHtml);
                        skipTillEnd($el);
                        break;
                    case 'giphy':
                        if ($node = xpQueryOne($xp, '/d:eztemplate/d:ezconfig/d:ezvalue[@key="embed"]')) {
                            $embedHtml = html_entity_decode($node->nodeValue);
                            $embedHtml = fixAmpersand($embedHtml);
                            list($idoc, $ixp) = loadHtmlToDomWithXpath($embedHtml);
                            /** @var $ixp \DOMXPath */
                            if ($giphyUrl = xpQueryOne($ixp, '//a[contains(@href, "giphy")]/@href')) {
                                $writer->writeRaw($giphyUrl->nodeValue);
                            }
                        };
                        skipTillEnd($el);
                        break;
                    case 'anchor':
                        $val = $xp->query('/d:eztemplate/d:ezconfig/d:ezvalue[@key="id"]');
                        $anchorId = $val->item(0)->nodeValue;
                        $writer->startElement('a');
                        $writer->writeAttribute('id', $anchorId);
                        $writer->fullEndElement();
                        skipTillEnd($el);
                        break;
                    case 'newsletter_registration':
                    case 'parent_money_calculator':
                    case 'immunization':
                    case 'percentiles_calculator':
                    case 'birthday_calculator':
                    case 'ovulation_calculator':
                    case 'development_calculator':
                        $writer->text("[{$templateName}]");
                        skipTillEnd($el);
                        break;
                    default:
                        $converter->log('unknown eztemplate template name [%s]', $templateName);
                }
                $writer->writeElement('br');
                $writer->writeRaw(PHP_EOL . PHP_EOL . PHP_EOL);
            }
        },
    ];
}