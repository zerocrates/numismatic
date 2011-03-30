<?php
/**
 * @package Numismatic
 * @copyright John Flatness, 2011
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPLv3 or any later version
 */

/**
 * Numismatic is a COinS collector for PHP.
 *
 * Reads COinS spans from HTML and can output the ContextObjects themselves,
 * OpenURLs, the key-value pairs from the ContextObject, or just the referent
 * metadata (the metadata about the object the COinS is describing).
 *
 * Requires ext/dom to be useful for much.
 *
 * @package Numismatic
 */
class Numismatic
{
    const COINS_CLASS = 'Z3988';
    const OPENURL_VER = 'Z39.88-2004';

    /**
     * Current ContextObject strings.
     *
     * @var array
     */
    private $contextObjects;

    /**
     * Parse an HTML or XML string for COinS spans.
     *
     * @param string $string
     * @param boolean $xml Whether the input string should be parsed as XML.
     */
    public function loadString($string, $xml = false)
    {
        $dom = new DOMDocument;

        if ($xml) {
            $dom->loadXML($string);
        } else {
            $dom->loadHTML($string);
        }

        $this->loadDOM($dom);
    }

    /**
     * Parse an HTML or XML file for COinS spans.
     *
     * @param string $path
     * @param boolean $xml Whether the input file should be parsed as XML.
     */
    public function loadFile($path, $xml = false)
    {
        $dom = new DOMDocument;

        if ($xml) {
            $dom->load($path);
        } else {
            $dom->loadHTMLFile($path);
        }

        $this->loadDOM($dom);
    }

    /**
     * Parse a DomDocument for COinS spans.
     *
     * @param DomDocument $dom
     */
    public function loadDOM(DomDocument $dom)
    {
        $this->contextObjects = $this->findContextObjects($dom);
    }

    /**
     * Parse ContextObjects out of a DOMDocument.
     *
     * @param DomDocument $dom
     * @return array Detected ContextObjects
     */
    private function findContextObjects(DomDocument $dom)
    {
        $xpath = new DOMXPath($dom);
        $spans = $xpath->query('//span[@class="' . self::COINS_CLASS . '"]');

        $titles = array();
        foreach ($spans as $span) {
            $titles[] = $span->getAttribute('title');
        }
        return $titles;
    }

    /**
     * Return the ContextObject strings detected from the input.
     *
     * @return array
     */
    public function getContextObjects()
    {
        return $this->contextObjects;
    }

    /**
     * Return an OpenURL for each COinS span in the input.
     *
     * @param string $baseURL OpenURL resolver URL.
     * @return array Array of OpenURLs pointing to the given resolver.
     */
    public function getOpenURLs($baseURL)
    {
        $urls = array();
        foreach ($this->contextObjects as $ctx) {
            $urls[] = "{$baseURL}?url_ver=" . self::OPENURL_VER . "&{$ctx}";
        }
        return $urls;
    }

    /**
     * Return the key-value pairs for each COinS span in the input.
     *
     * @return array
     */
    public function getKeyValuePairs()
    {
        $arrays = array();
        foreach ($this->contextObjects as $ctx) {
            $arrays[] = self::parseContextObject($ctx);
        }
        return $arrays;
    }

    public function getMetadata()
    {
        $metadataArrays = array();
        foreach ($this->contextObjects as $ctx) {
            $metadataArrays[] = self::parseContextObjectMetadata($ctx);
        }
        return $metadataArrays;
    }

    /**
     * Parse a ContextObject into urldecoded key-value pairs.
     * 
     * Keys with multiple values are represented by an array.
     *
     * @param string $ctx ContextObject
     * @return array
     */
    public static function parseContextObject($ctx)
    {
        $ctxArray = array();
        $pairs = explode('&', $ctx);
        foreach ($pairs as $pair) {
            list($key, $value) = explode('=', $pair);
            $value = urldecode($value);
            if (array_key_exists($key, $ctxArray)) {
                if (is_array($ctxArray[$key])) {
                    $ctxArray[$key][] = $value;
                } else {
                    $ctxArray[$key] = array($ctxArray[$key], $value);
                }
            } else {
                $ctxArray[$key] = $value;
            }
        }
        return $ctxArray;
    }

    /**
     * Parse the referent metadata from a ContextObject into an array.
     *
     * 'id' is the referent id (usually a DOI or URL),
     * 'format' is the metadata format identifier,
     * 'metadata' is an array of the metadata key-value pairs.
     *
     * @param string $ctx ContextObject
     * @return array
     */
    public static function parseContextObjectMetadata($ctx)
    {
        $metadataArray = array();
        $ctxArray = self::parseContextObject($ctx);
        foreach ($ctxArray as $key => $value) {
            if ($key == 'rft_id') {
                $metadataArray['id'] = $value;
            } else if ($key == 'rft_val_fmt') {
                $metadataArray['format'] = $value;
            } else if (strncmp('rft.', $key, 4) == 0) {
                $newKey = substr($key, 4);
                $metadataArray['metadata'][$newKey] = $value;
            }
        }
        return $metadataArray;
    }
}
