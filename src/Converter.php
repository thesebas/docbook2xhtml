<?php

namespace thesebas\docbook2html;

class Converter
{
    /**
     * @var \XMLWriter
     */
    private $xmlWriter;

    /**
     * @var \XMLReader
     */
    private $xmlReader;

    /**
     * @var array
     */
    private $callbacks;

    private $verboseLevel = 0;

    public function __construct($verboseLevel)
    {
        $this->verboseLevel = $verboseLevel;
        $this->xmlReader = new \XMLReader();
        $this->xmlWriter = new \XMLWriter();

        $this->callbacks = map($this);
    }

    public function convertFile($filename)
    {
        $this->xmlReader->open($filename);
    }

    public function convertString($xml)
    {
        $this->xmlReader->XML($xml);
    }

    /**
     * @return \XMLWriter
     */
    public function getXmlWriter()
    {
        return $this->xmlWriter;
    }

    public function log($format, ...$args)
    {
        if ($this->verboseLevel > 0) {
            fprintf(STDERR, $format, ...$args);
        }
    }

    public function debug($format, ...$args)
    {
        if ($this->verboseLevel > 1) {
            fprintf(STDERR, $format, ...$args);
        }
    }

    protected function convert()
    {
        $this->xmlWriter->openMemory();

        $stack = [];
        $isOpen = null;
        $reader = $this->xmlReader;
        $callbacks = $this->callbacks;
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
            $this->debug(str_repeat(" ", $reader->depth) . join(' $ ', [
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
                $this->log("!!! unknown tag [%s:%s]" . PHP_EOL, $reader->namespaceURI, $reader->name);
            }

            if ($popAtTheEnd) {
                array_pop($stack);
            }
        }
    }
}
