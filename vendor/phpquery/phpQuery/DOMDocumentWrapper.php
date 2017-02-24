<?php

namespace phpQuery;

/**
 * DOMDocumentWrapper class simplifies work with DOMDocument.
 *
 * Know bug:
 * - in XHTML fragments, <br /> changes to <br clear="none" />
 *
 * @todo check XML catalogs compatibility
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * @package phpQuery
 */
class DOMDocumentWrapper
{
    /**
     * @var \DOMDocument
     */
    public $document;
    public $id;
    public $contentType = '';
    /**
     * @var \DOMXPath
     */
    public $xpath;
    public $uuid = 0;
    public $data = [];
    public $dataNodes = [];
    public $events = [];
    public $eventsNodes = [];
    public $eventsGlobal = [];
    public $frames = [];
    /**
     * Document root, by default equals to document itself.
     * Used by documentFragments.
     * @var \DOMNode
     */
    public $root;
    public $isDocumentFragment;
    public $isXML = false;
    public $isXHTML = false;
    public $isHTML = false;
    public $charset;

    public function __construct($markup = null, $contentType = null, $newDocumentID = null)
    {
        if (isset($markup)) {
            $this->load($markup, $contentType);
        }
        $this->id = $newDocumentID ? $newDocumentID : md5(microtime());
    }

    public function load($markup, $contentType = null)
    {
        $this->contentType = strtolower($contentType);
        if ($markup instanceof \DOMDocument) {
            $this->document = $markup;
            $this->root = $this->document;
            $this->charset = $this->document->encoding;
        } else if ($this->loadMarkup($markup)) {
            $this->document->preserveWhiteSpace = true;
            $this->xpath = new \DOMXPath($this->document);
            $this->afterMarkupLoad();
            return true;
        }
        return false;
    }

    protected function afterMarkupLoad()
    {
        if ($this->isXHTML) {
            $this->xpath->registerNamespace("html", "http://www.w3.org/1999/xhtml");
        }
    }

    protected function loadMarkup($markup)
    {
        $loaded = false;
        if ($this->contentType) {
            phpQuery::debug("Load markup for content type {$this->contentType}");
            list($contentType, $charset) = $this->contentTypeToArray($this->contentType);
            switch ($contentType) {
                case 'text/html':
                    phpQuery::debug("Loading HTML, content type '{$this->contentType}'");
                    $loaded = $this->loadMarkupHTML($markup, $charset);
                    break;
                case 'text/xml':
                case 'application/xhtml+xml':
                    phpQuery::debug("Loading XML, content type '{$this->contentType}'");
                    $loaded = $this->loadMarkupXML($markup, $charset);
                    break;
                default:
                    if (strpos('xml', $this->contentType) !== false) {
                        phpQuery::debug("Loading XML, content type '{$this->contentType}'");
                        $loaded = $this->loadMarkupXML($markup, $charset);
                    } else
                        phpQuery::debug("Could not determine document type from content type '{$this->contentType}'");
            }
        } else {
            if ($this->isXML($markup)) {
                phpQuery::debug("Loading XML, isXML() == true");
                $loaded = $this->loadMarkupXML($markup);
                if (!$loaded && $this->isXHTML) {
                    phpQuery::debug('Loading as XML failed, trying to load as HTML, isXHTML == true');
                    $loaded = $this->loadMarkupHTML($markup);
                }
            } else {
                phpQuery::debug("Loading HTML, isXML() == false");
                $loaded = $this->loadMarkupHTML($markup);
            }
        }
        return $loaded;
    }

    protected function loadMarkupReset()
    {
        $this->isXML = $this->isXHTML = $this->isHTML = false;
    }

    protected function documentCreate($charset, $version = '1.0')
    {
        if (!$version){
            $version = '1.0';
        }
        $this->document = new \DOMDocument($version, $charset);
        $this->charset = $this->document->encoding;
//		$this->document->encoding = $charset;
        $this->document->formatOutput = true;
        $this->document->preserveWhiteSpace = true;
    }

    protected function loadMarkupHTML($markup, $requestedCharset = null)
    {
        $this->loadMarkupReset();
        $this->isHTML = true;
        if (!isset($this->isDocumentFragment)) {
            $this->isDocumentFragment = self::isDocumentFragmentHTML($markup);
        }
        $charset = null;
        $documentCharset = $this->charsetFromHTML($markup);
        $addDocumentCharset = false;
        if ($documentCharset) {
            $charset = $documentCharset;
            $markup = $this->charsetFixHTML($markup);
        } else if ($requestedCharset) {
            $charset = $requestedCharset;
        }
        if (!$charset){
            $charset = phpQuery::$defaultCharset;
        }
        if (!$documentCharset) {
            $documentCharset = 'utf-8';
            $addDocumentCharset = true;
        }
        // Should be careful here, still need 'magic encoding detection' since lots of pages have other 'default encoding'
        // Worse, some pages can have mixed encodings... we'll try not to worry about that
        $requestedCharset = strtoupper($requestedCharset);
        $documentCharset = strtoupper($documentCharset);
        phpQuery::debug("DOC: $documentCharset REQ: $requestedCharset");
        if ($requestedCharset && $documentCharset && $requestedCharset !== $documentCharset) {
            phpQuery::debug("CHARSET CONVERT");
            // Document Encoding Conversion
            // http://code.google.com/p/phpquery/issues/detail?id=86
            if (function_exists('mb_detect_encoding')) {
                $possibleCharsets = [$documentCharset, $requestedCharset, 'AUTO'];
                $docEncoding = mb_detect_encoding($markup, implode(', ', $possibleCharsets));
                if (!$docEncoding)
                    $docEncoding = $documentCharset; // ok trust the document
                phpQuery::debug("DETECTED '$docEncoding'");
                // Detected does not match what document says...
                if ($docEncoding !== $documentCharset) {
                    // Tricky..
                }
                if ($docEncoding !== $requestedCharset) {
                    phpQuery::debug("CONVERT $docEncoding => $requestedCharset");
                    $markup = mb_convert_encoding($markup, $requestedCharset, $docEncoding);
                    $markup = $this->charsetAppendToHTML($markup, $requestedCharset);
                    $charset = $requestedCharset;
                }
            } else {
                phpQuery::debug("TODO: charset conversion without mbstring...");
            }
        }
        if ($this->isDocumentFragment) {
            phpQuery::debug("Full markup load (HTML), DocumentFragment detected, using charset '$charset'");
            $return = $this->documentFragmentLoadMarkup($this, $charset, $markup);
        } else {
            if ($addDocumentCharset) {
                phpQuery::debug("Full markup load (HTML), appending charset: '$charset'");
                $markup = $this->charsetAppendToHTML($markup, $charset);
            }
            phpQuery::debug("Full markup load (HTML), documentCreate('$charset')");
            $this->documentCreate($charset);
            /**
             * @todo 错误抑制最好去掉
             */
            $return = phpQuery::$debug ? $this->document->loadHTML($markup) : @$this->document->loadHTML($markup);
            if ($return) {
                $this->root = $this->document;
            }
        }
        if ($return && !$this->contentType) {
            $this->contentType = 'text/html';
        }
        return $return;
    }

    protected function loadMarkupXML($markup, $requestedCharset = null)
    {
        phpQuery::debug('Full markup load (XML): ' . substr($markup, 0, 250));
        $this->loadMarkupReset();
        $this->isXML = true;
        // check agains XHTML in contentType or markup
        $isContentTypeXHTML = $this->isXHTML();
        $isMarkupXHTML = $this->isXHTML($markup);
        if ($isContentTypeXHTML || $isMarkupXHTML) {
            phpQuery::debug('Full markup load (XML), XHTML detected');
            $this->isXHTML = true;
        }
        // determine document fragment
        if (!isset($this->isDocumentFragment)) {
            $this->isDocumentFragment = $this->isXHTML ? self::isDocumentFragmentXHTML($markup) : self::isDocumentFragmentXML($markup);
        }
        // this charset will be used
        $charset = null;
        // charset from XML declaration @var string
        $documentCharset = $this->charsetFromXML($markup);
        if (!$documentCharset) {
            if ($this->isXHTML) {
                // this is XHTML, try to get charset from content-type meta header
                $documentCharset = $this->charsetFromHTML($markup);
                if ($documentCharset) {
                    phpQuery::debug("Full markup load (XML), appending XHTML charset '$documentCharset'");
                    $this->charsetAppendToXML($markup, $documentCharset);
                    $charset = $documentCharset;
                }
            }
            if (!$documentCharset) {
                // if still no document charset...
                $charset = $requestedCharset;
            }
        } else if ($requestedCharset) {
            $charset = $requestedCharset;
        }
        if (!$charset) {
            $charset = phpQuery::$defaultCharset;
        }
        if ($requestedCharset && $documentCharset && $requestedCharset != $documentCharset) {
            // TODO place for charset conversion
            //			$charset = $requestedCharset;
        }
        if ($this->isDocumentFragment) {
            phpQuery::debug("Full markup load (XML), DocumentFragment detected, using charset '$charset'");
            $return = $this->documentFragmentLoadMarkup($this, $charset, $markup);
        } else {
            // FIXME ???
            if ($isContentTypeXHTML && !$isMarkupXHTML)
                if (!$documentCharset) {
                    phpQuery::debug("Full markup load (XML), appending charset '$charset'");
                    $markup = $this->charsetAppendToXML($markup, $charset);
                }
            // see http://pl2.php.net/manual/en/book.dom.php#78929
            // LIBXML_DTDLOAD (>= PHP 5.1)
            // does XML ctalogues works with LIBXML_NONET
            //		$this->document->resolveExternals = true;
            // TODO test LIBXML_COMPACT for performance improvement
            // create document
            $this->documentCreate($charset);
            $libxmlStatic = phpQuery::$debug === 2 ? LIBXML_DTDLOAD | LIBXML_DTDATTR | LIBXML_NONET : LIBXML_DTDLOAD | LIBXML_DTDATTR | LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR;
            $return = $this->document->loadXML($markup, $libxmlStatic);
            if ($return) {
                $this->root = $this->document;
            }
        }
        if ($return) {
            if (!$this->contentType) {
                if ($this->isXHTML)
                    $this->contentType = 'application/xhtml+xml';
                else
                    $this->contentType = 'text/xml';
            }
            return $return;
        } else {
            throw new \Exception("Error loading XML markup");
        }
    }

    protected function isXHTML($markup = null)
    {
        if (!isset($markup)) {
            return strpos($this->contentType, 'xhtml') !== false;
        }
        return strpos($markup, "<!DOCTYPE html") !== false;
    }

    protected function isXML($markup)
    {
        return strpos(substr($markup, 0, 100), '<' . '?xml') !== false;
    }

    protected function contentTypeToArray($contentType)
    {
        $test = null;
        $matches = explode(';', trim(strtolower($contentType)));
        if (isset($matches[1])) {
            $matches[1] = explode('=', $matches[1]);
            $matches[1] = isset($matches[1][1]) && trim($matches[1][1])
                ? $matches[1][1]
                : $matches[1][0];
        } else
            $matches[1] = null;
        return $matches;
    }

    /**
     * @param $markup
     * @return array contentType, charset
     */
    protected function contentTypeFromHTML($markup)
    {
        $matches = [];
        // find meta tag
        preg_match('@<meta[^>]+http-equiv\\s*=\\s*(["|\'])Content-Type\\1([^>]+?)>@i', $markup, $matches);
        if (!isset($matches[0])) {
            return array(null, null);
        }
        // get attr 'content'
        preg_match('@content\\s*=\\s*(["|\'])(.+?)\\1@', $matches[0], $matches);
        if (!isset($matches[0])) {
            return array(null, null);
        }
        return $this->contentTypeToArray($matches[2]);
    }

    protected function charsetFromHTML($markup)
    {
        $contentType = $this->contentTypeFromHTML($markup);
        return $contentType[1];
    }

    protected function charsetFromXML($markup)
    {
        $matches = [];
        // find declaration
        preg_match('@<' . '?xml[^>]+encoding\\s*=\\s*(["|\'])(.*?)\\1@i',
            $markup, $matches
        );
        return isset($matches[2]) ? strtolower($matches[2]) : null;
    }

    /**
     * Repositions meta[type=charset] at the start of head. Bypasses DOMDocument bug.
     *
     * @link http://code.google.com/p/phpquery/issues/detail?id=80
     * @param $markup string
     * @return string
     */
    protected function charsetFixHTML($markup)
    {
        $matches = [];
        // find meta tag
        preg_match('@\s*<meta[^>]+http-equiv\\s*=\\s*(["|\'])Content-Type\\1([^>]+?)>@i',
            $markup, $matches, PREG_OFFSET_CAPTURE
        );
        if (!isset($matches[0])) {
            return '';
        }
        $metaContentType = $matches[0][0];
        $markup = substr($markup, 0, $matches[0][1]) . substr($markup, $matches[0][1] + strlen($metaContentType));
        $headStart = stripos($markup, '<head>');
        $markup = substr($markup, 0, $headStart + 6) . $metaContentType . substr($markup, $headStart + 6);
        return $markup;
    }

    protected function charsetAppendToHTML($html, $charset, $xhtml = false)
    {
        // remove existing meta[type=content-type]
        $html = preg_replace('@\s*<meta[^>]+http-equiv\\s*=\\s*(["|\'])Content-Type\\1([^>]+?)>@i', '', $html);
        $meta = '<meta http-equiv="Content-Type" content="text/html;charset='
            . $charset . '" '
            . ($xhtml ? '/' : '')
            . '>';
        if (strpos($html, '<head') === false) {
            if (strpos($html, '<html') === false) {
                return $meta . $html;
            } else {
                return preg_replace(
                    '@<html(.*?)(?(?<!\?)>)@s',
                    "<html\\1><head>{$meta}</head>",
                    $html
                );
            }
        } else {
            return preg_replace(
                '@<head(.*?)(?(?<!\?)>)@s',
                '<head\\1>' . $meta,
                $html
            );
        }
    }

    protected function charsetAppendToXML($markup, $charset)
    {
        $declaration = '<' . '?xml version="1.0" encoding="' . $charset . '"?' . '>';
        return $declaration . $markup;
    }

    public static function isDocumentFragmentHTML($markup)
    {
        return stripos($markup, '<html') === false && stripos($markup, '<!doctype') === false;
    }

    public static function isDocumentFragmentXML($markup)
    {
        return stripos($markup, '<' . '?xml') === false;
    }

    public static function isDocumentFragmentXHTML($markup)
    {
        return self::isDocumentFragmentHTML($markup);
    }

    public function importAttr($value)
    {
        // TODO
    }

    /**
     * @param $source
     * @param null $sourceCharset
     * @return array
     * @throws \Exception
     */
    public function import($source, $sourceCharset = null)
    {
        // TODO charset conversions
        $return = [];
        if ($source instanceof \DOMNode && !($source instanceof \DOMNodeList)){
            $source = array($source);
        }
        if (is_array($source) || $source instanceof \DOMNodeList) {
            // dom nodes
            phpQuery::debug('Importing nodes to document');
            foreach ($source as $node) {
                $return[] = $this->document->importNode($node, true);
            }
        } else {
            // string markup
            $fake = $this->documentFragmentCreate($source, $sourceCharset);
            if ($fake === false) {
                throw new \Exception("Error loading documentFragment markup");
            } else {
                return $this->import($fake->root->childNodes);
            }
        }
        return $return;
    }

    /**
     * Creates new document fragment.
     * @param $source
     * @param $charset
     * @return DOMDocumentWrapper|boolean
     */
    protected function documentFragmentCreate($source, $charset = null)
    {
        $fake = new DOMDocumentWrapper();
        $fake->contentType = $this->contentType;
        $fake->isXML = $this->isXML;
        $fake->isHTML = $this->isHTML;
        $fake->isXHTML = $this->isXHTML;
        $fake->root = $fake->document;
        if (!$charset) {
            $charset = $this->charset;
        }
        if ($source instanceof \DOMNode && !($source instanceof \DOMNodeList)) {
            $source = [$source];
        }
        if (is_array($source) || $source instanceof \DOMNodeList) {
            if (!$this->documentFragmentLoadMarkup($fake, $charset)) {
                return false;
            }
            $nodes = $fake->import($source);
            foreach ($nodes as $node) {
                $fake->root->appendChild($node);
            }
        } else {
            // string markup
            $this->documentFragmentLoadMarkup($fake, $charset, $source);
        }
        return $fake;
    }

    /**
     * @param $fragment DOMDocumentWrapper
     * @param $charset
     * @param $markup
     * @return string
     */
    private function documentFragmentLoadMarkup($fragment, $charset, $markup = null)
    {
        // TODO error handling
        // TODO copy doctype
        // tempolary turn off
        $fragment->isDocumentFragment = false;
        if ($fragment->isXML) {
            if ($fragment->isXHTML) {
                // add FAKE element to set default namespace
                $fragment->loadMarkupXML('<?xml version="1.0" encoding="' . $charset . '"?>'
                    . '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" '
                    . '"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'
                    . '<fake xmlns="http://www.w3.org/1999/xhtml">' . $markup . '</fake>');
                $fragment->root = $fragment->document->firstChild->nextSibling;
            } else {
                $fragment->loadMarkupXML('<?xml version="1.0" encoding="' . $charset . '"?><fake>' . $markup . '</fake>');
                $fragment->root = $fragment->document->firstChild;
            }
        } else {
            $markup2 = phpQuery::$defaultDoctype . '<html><head><meta http-equiv="Content-Type" content="text/html;charset='
                . $charset . '"></head>';
            $noBody = strpos($markup, '<body') === false;
            if ($noBody) {
                $markup2 .= '<body>';
            }
            $markup2 .= $markup;
            if ($noBody) {
                $markup2 .= '</body>';
            };
            $markup2 .= '</html>';
            $fragment->loadMarkupHTML($markup2);
            // TODO resolv body tag merging issue
            $fragment->root = $noBody
                ? $fragment->document->firstChild->nextSibling->firstChild->nextSibling
                : $fragment->document->firstChild->nextSibling->firstChild->nextSibling;
        }
        if (!$fragment->root) {
            return false;
        }

        $fragment->isDocumentFragment = true;
        return true;
    }

    /**
     * @param $fragment DOMDocumentWrapper
     * @return string
     */
    protected function documentFragmentToMarkup($fragment)
    {
        phpQuery::debug('documentFragmentToMarkup');
        $tmp = $fragment->isDocumentFragment;
        $fragment->isDocumentFragment = false;
        $markup = $fragment->markup();
        if ($fragment->isXML) {
            $markup = substr($markup, 0, strrpos($markup, '</fake>'));
            if ($fragment->isXHTML) {
                $markup = substr($markup, strpos($markup, '<fake') + 43);
            } else {
                $markup = substr($markup, strpos($markup, '<fake>') + 6);
            }
        } else {
            $markup = substr($markup, strpos($markup, '<body>') + 6);
            $markup = substr($markup, 0, strrpos($markup, '</body>'));
        }
        $fragment->isDocumentFragment = $tmp;
        if (phpQuery::$debug)
            phpQuery::debug('documentFragmentToMarkup: ' . substr($markup, 0, 150));
        return $markup;
    }

    /**
     * Return document markup, starting with optional $nodes as root.
     * @param $nodes    \DOMNode|\DOMNodeList
     * @param $innerMarkup boolean
     * @return string
     */
    public function markup($nodes = null, $innerMarkup = false)
    {
        if (isset($nodes) && count($nodes) == 1 && $nodes[0] instanceof \DOMDocument) {
            $nodes = null;
        }
        if (isset($nodes)) {
            $markup = '';
            if (!is_array($nodes) && !($nodes instanceof \DOMNodeList)) {
                $nodes = array($nodes);
            }
            if ($this->isDocumentFragment && !$innerMarkup) {
                foreach ($nodes as $i => $node) {
                    if ($node->isSameNode($this->root)) {
                        $nodes = array_slice($nodes, 0, $i)
                            + phpQuery::DOMNodeListToArray($node->childNodes)
                            + array_slice($nodes, $i + 1);
                    }
                }
            }
            if ($this->isXML && !$innerMarkup) {
                phpQuery::debug("Getting outerXML with charset '{$this->charset}'");
                // we need outerXML, so we can benefit from
                // $node param support in saveXML()
                foreach ($nodes as $node)
                    $markup .= $this->document->saveXML($node);
            } else {
                $loop = [];
                if ($innerMarkup)
                    foreach ($nodes as $node) {
                        if ($node->childNodes)
                            foreach ($node->childNodes as $child)
                                $loop[] = $child;
                        else
                            $loop[] = $node;
                    }
                else
                    $loop = $nodes;
                phpQuery::debug("Getting markup, moving selected nodes (" . count($loop) . ") to new DocumentFragment");
                $fake = $this->documentFragmentCreate($loop);
                $markup = $this->documentFragmentToMarkup($fake);
            }
            if ($this->isXHTML) {
                phpQuery::debug("Fixing XHTML");
                $markup = self::markupFixXHTML($markup);
            }
            phpQuery::debug("Markup: " . substr($markup, 0, 250));
            return $markup;
        } else {
            if ($this->isDocumentFragment) {
                // documentFragment, html only...
                phpQuery::debug("Getting markup, DocumentFragment detected");
//				return $this->markup(
////					$this->document->getElementsByTagName('body')->item(0)
//					$this->document->root, true
//				);
                $markup = $this->documentFragmentToMarkup($this);
                // no need for markupFixXHTML, as it's done thought markup($nodes) method
                return $markup;
            } else {
                phpQuery::debug("Getting markup (" . ($this->isXML ? 'XML' : 'HTML') . "), final with charset '{$this->charset}'");
                $markup = $this->isXML
                    ? $this->document->saveXML()
                    : $this->document->saveHTML();
                if ($this->isXHTML) {
                    phpQuery::debug("Fixing XHTML");
                    $markup = self::markupFixXHTML($markup);
                }
                phpQuery::debug("Markup: " . substr($markup, 0, 250));
                return $markup;
            }
        }
    }

    protected static function markupFixXHTML($markup)
    {
        $markup = self::expandEmptyTag('script', $markup);
        $markup = self::expandEmptyTag('select', $markup);
        $markup = self::expandEmptyTag('textarea', $markup);
        return $markup;
    }

    /**
     * expandEmptyTag
     * @param $tag
     * @param $xml
     * @return string
     * @author mjaque at ilkebenson dot com
     * @link http://php.net/manual/en/domdocument.savehtml.php#81256
     */
    public static function expandEmptyTag($tag, $xml)
    {
        $indice = 0;
        while ($indice < strlen($xml)) {
            $pos = strpos($xml, "<$tag ", $indice);
            if ($pos) {
                $posCierre = strpos($xml, ">", $pos);
                if ($xml[$posCierre - 1] == "/") {
                    $xml = substr_replace($xml, "></$tag>", $posCierre - 1, 2);
                }
                $indice = $posCierre;
            } else break;
        }
        return $xml;
    }
}