<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

class PwgXmlWriter
{
    public bool $_indent;

    public string $_indentStr;

    public array $_elementStack;

    public bool $_lastTagOpen;

    public int $_indentLevel;

    public string $_encodedXml;

    public function __construct()
    {
        $this->_elementStack = [];
        $this->_lastTagOpen = false;
        $this->_indentLevel = 0;

        $this->_encodedXml = '';
        $this->_indent = true;
        $this->_indentStr = "\t";
    }

    public function &getOutput(): string
    {
        return $this->_encodedXml;
    }

    public function start_element(
        string $name
    ): void {
        $this->_end_prev(false);
        if (! empty($this->_elementStack)) {
            $this->_eol_indent();
        }
        $this->_indentLevel++;
        $this->_indent();
        $diff = ord($name[0]) - ord('0');
        if ($diff >= 0 && $diff <= 9) {
            $name = '_' . $name;
        }
        $this->_output('<' . $name);
        $this->_lastTagOpen = true;
        $this->_elementStack[] = $name;
    }

    public function end_element(
        string $x
    ): void {
        $close_tag = $this->_end_prev(true);
        $name = array_pop($this->_elementStack);
        if ($close_tag) {
            $this->_indentLevel--;
            $this->_indent();
            //      $this->_eol_indent();
            $this->_output('</' . $name . '>');
        }
    }

    public function write_content(
        string $value
    ): void {
        $this->_end_prev(false);
        $value = (string) $value;
        $this->_output(htmlspecialchars($value));
    }

    public function write_cdata(
        string $value
    ): void {
        $this->_end_prev(false);
        $value = (string) $value;
        $this->_output(
            '<![CDATA['
      . str_replace(']]>', ']]&gt;', $value)
      . ']]>'
        );
    }

    public function write_attribute(
        string $name,
        string $value
    ): void {
        $this->_output(' ' . $name . '="' . $this->encode_attribute($value) . '"');
    }

    public function encode_attribute(
        string $value
    ): string {
        return htmlspecialchars((string) $value);
    }

    public function _end_prev(
        bool $done
    ): bool {
        $ret = true;
        if ($this->_lastTagOpen) {
            if ($done) {
                $this->_indentLevel--;
                $this->_output(' />');
                //$this->_eol_indent();
                $ret = false;
            } else {
                $this->_output('>');
            }
            $this->_lastTagOpen = false;
        }
        return $ret;
    }

    public function _eol_indent(): void
    {
        if ($this->_indent) {
            $this->_output("\n");
        }
    }

    public function _indent(): void
    {
        if ($this->_indent and
            $this->_indentLevel > count($this->_elementStack)) {
            $this->_output(
                str_repeat($this->_indentStr, count($this->_elementStack))
            );
        }
    }

    public function _output(
        string $raw_content
    ): void {
        $this->_encodedXml .= $raw_content;
    }
}

class PwgRestEncoder extends PwgResponseEncoder
{
    private PwgXmlWriter $_writer;

    #[\Override]
    public function encodeResponse(
        array|bool|string|PwgError|null $response
    ): string {
        if ($response instanceof PwgError) {
            $ret = '<?xml version="1.0"?>
<rsp stat="fail">
	<err code="' . $response->code() . '" msg="' . htmlspecialchars($response->message()) . '" />
</rsp>';
            return $ret;
        }

        $this->_writer = new PwgXmlWriter();
        $this->encode($response);
        $ret = $this->_writer->getOutput();
        $ret = '<?xml version="1.0" encoding="utf-8" ?>
<rsp stat="ok">
' . $ret . '
</rsp>';

        return $ret;
    }

    #[\Override]
    public function getContentType(): string
    {
        return 'text/xml';
    }

    public function encode_array(
        array $data,
        string $itemName,
        array $xml_attributes = []
    ): void {
        foreach ($data as $item) {
            $this->_writer->start_element($itemName);
            $this->encode($item, $xml_attributes);
            $this->_writer->end_element($itemName);
        }
    }

    public function encode_struct(
        array $data,
        bool $skip_underscore,
        array $xml_attributes = []
    ): void {
        foreach ($data as $name => $value) {
            if (is_numeric($name)) {
                continue;
            }
            if ($skip_underscore and $name[0] == '_') {
                continue;
            }
            if ($value === null) {
                continue;
            } // null means we dont put it
            if ($name == WS_XML_ATTRIBUTES) {
                foreach ($value as $attr_name => $attr_value) {
                    $this->_writer->write_attribute($attr_name, $attr_value);
                }
                unset($data[$name]);
            } elseif (isset($xml_attributes[$name])) {
                $this->_writer->write_attribute($name, $value);
                unset($data[$name]);
            }
        }

        foreach ($data as $name => $value) {
            if (is_numeric($name)) {
                continue;
            }
            if ($skip_underscore and $name[0] == '_') {
                continue;
            }
            if ($value === null) {
                continue;
            } // null means we dont put it
            $this->_writer->start_element($name);
            $this->encode($value);
            $this->_writer->end_element($name);
        }
    }

    public function encode(
        mixed $data,
        array $xml_attributes = []
    ): void {
        switch (gettype($data)) {
            case 'null':
            case 'NULL':
                $this->_writer->write_content('');
                break;
            case 'boolean':
                $this->_writer->write_content($data ? '1' : '0');
                break;
            case 'integer':
            case 'double':
                $this->_writer->write_content($data);
                break;
            case 'string':
                $this->_writer->write_content($data);
                break;
            case 'array':
                $is_array = range(0, count($data) - 1) === array_keys($data);
                if ($is_array) {
                    $this->encode_array($data, 'item');
                } else {
                    $this->encode_struct($data, false, $xml_attributes);
                }
                break;
            case 'object':
                switch (strtolower(@get_class($data))) {
                    case 'pwgnamedarray':
                        $this->encode_array($data->_content, $data->_itemName, $data->_xmlAttributes);
                        break;
                    case 'pwgnamedstruct':
                        $this->encode_struct($data->_content, false, $data->_xmlAttributes);
                        break;
                    default:
                        $this->encode_struct(get_object_vars($data), true);
                        break;
                }
                break;
            default:
                trigger_error('Invalid type ' . gettype($data) . ' ' . @get_class($data), E_USER_WARNING);
        }
    }
}
