<?php

namespace phpQuery;

use DOMNodeList;
use phpQuery\Callback\Callback;
use phpQuery\Callback\CallbackParameterToReference;
use phpQuery\Callback\CallbackParam;
use DOMNode;
use DOMDocument;

class phpQuery
{

    public static $documents = [];

    public static $debug = false;

    public static $defaultCharset = 'UTF-8';

    public static $defaultDocumentID;

    public static $defaultDoctype = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">';

    public static $mbstringSupport = true;

    public static $dumpCount = 0;

    public static $extendMethods = [];

    public static $pluginsMethods = [];

    /**
     * @param $markup
     * @param $contentType
     * @param $unsetMemoy
     * @return phpQueryObject
     */
    public static function newDocument($markup = null, $contentType = null,$unsetMemoy = false)
    {
        if (!$markup) {
            $markup = '';
        }
        if($unsetMemoy){
            self::$documents = [];
        }
        $documentID = self::createDocumentWrapper($markup, $contentType);
        return new phpQueryObject($documentID);
    }

    /**
     * @param $html
     * @param null $contentType
     * @param null $documentID
     * @return mixed
     */
    protected static function createDocumentWrapper($html, $contentType = null, $documentID = null)
    {
        if ($html instanceof \DOMDocument) {
            if (!self::getDocumentID($html)) {
                $wrapper = new DOMDocumentWrapper($html, $contentType, $documentID);
            }
        } else {
            $wrapper = new DOMDocumentWrapper($html, $contentType, $documentID);
        }
        self::$documents[$wrapper->id] = $wrapper;
        self::selectDocument($wrapper->id);
        return $wrapper->id;
    }

    /**
     * Sets default document to $id. Document has to be loaded prior
     * to using this method.
     * $id can be retrived via getDocumentID() or getDocumentIDRef().
     * @param string $id
     */
    public static function selectDocument($id)
    {
        $id = self::getDocumentID($id);
        self::debug("Selecting document '$id' as default one");
        self::$defaultDocumentID = self::getDocumentID($id);
    }

    public static function debug($var)
    {
        if (self::$debug) {
            var_dump($var);
        }
    }

    public static function getDocumentID($source)
    {
        if ($source instanceof \DOMDocument) {
            foreach (self::$documents as $id => $document) {
                if ($source->isSameNode($document->document)) {
                    return $id;
                }
            }
        } else if ($source instanceof \DOMNode) {
            foreach (self::$documents as $id => $document) {
                if ($source->ownerDocument->isSameNode($document->document)) {
                    return $id;
                }
            }
        } else if ($source instanceof phpQueryObject) {
            return $source->getDocumentID();
        } else if (is_string($source) && isset(self::$documents[$source])) {
            return $source;
        }
    }

    public static function data($node, $name, $data, $documentID = null)
    {
        if (!$documentID) {
            $documentID = self::getDocumentID($node);
        }
        $document = self::$documents[$documentID];
        $node = self::dataSetupNode($node, $documentID);
        if (!isset($node->dataID)) {
            $node->dataID = ++self::$documents[$documentID]->uuid;
        }
        $id = $node->dataID;
        if (!isset($document->data[$id])) {
            $document->data[$id] = [];
        }
        if (!is_null($data)) {
            $document->data[$id][$name] = $data;
        }
        if ($name) {
            if (isset($document->data[$id][$name])) {
                return $document->data[$id][$name];
            }
        } else {
            return $id;
        }
    }

    /**
     * @param $callback Callback
     * @param $params
     * @param $paramStructure
     * @return void|bool
     */
    public static function callbackRun($callback, $params = [], $paramStructure = null)
    {
        if (!$callback) {
            return '';
        }
        if ($callback instanceof CallbackParameterToReference) {
            // TODO support ParamStructure to select which $param push to reference
            if (isset($params[0])) {
                $callback->callback = $params[0];
            }
            return true;
        }
        if ($callback instanceof Callback) {
            $paramStructure = $callback->params;
            $callback = $callback->callback;
        }
        if (!$paramStructure)
            return call_user_func_array($callback, $params);
        $p = 0;
        foreach ($paramStructure as $i => $v) {
            $paramStructure[$i] = $v instanceof CallbackParam
                ? $params[$p++]
                : $v;
        }
        return call_user_func_array($callback, $paramStructure);
    }

    /**
     * @param \DOMNode $node
     * @param string $documentID
     * @return mixed
     */
    protected static function dataSetupNode($node, $documentID)
    {
        // search are return if alredy exists
        foreach (self::$documents[$documentID]->dataNodes as $dataNode) {
            if ($node->isSameNode($dataNode)) {
                return $dataNode;
            }
        }
        self::$documents[$documentID]->dataNodes[] = $node;
        return $node;
    }

    /**
     * Multi-purpose function.
     * Use pq() as shortcut.
     *
     * In below examples, $pq is any result of pq(); function.
     *
     * 1. Import markup into existing document (without any attaching):
     * - Import into selected document:
     *   pq('<div/>')                // DOESNT accept text nodes at beginning of input string !
     * - Import into document with ID from $pq->getDocumentID():
     *   pq('<div/>', $pq->getDocumentID())
     * - Import into same document as DOMNode belongs to:
     *   pq('<div/>', DOMNode)
     * - Import into document from phpQuery object:
     *   pq('<div/>', $pq)
     *
     * 2. Run query:
     * - Run query on last selected document:
     *   pq('div.myClass')
     * - Run query on document with ID from $pq->getDocumentID():
     *   pq('div.myClass', $pq->getDocumentID())
     * - Run query on same document as DOMNode belongs to and use node(s)as root for query:
     *   pq('div.myClass', DOMNode)
     * - Run query on document from phpQuery object
     *   and use object's stack as root node(s) for query:
     *   pq('div.myClass', $pq)
     *
     * @param string|DOMNode|DOMNodeList|array $arg1 HTML markup, CSS Selector, DOMNode or array of DOMNodes
     * @param string|phpQueryObject|DOMNode $context DOM ID from $pq->getDocumentID(), phpQuery object (determines also query root) or DOMNode (determines also query root)
     *
     * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery|QueryTemplatesPhpQuery|false
     * phpQuery object or false in case of error.
     */
    public static function pq($arg1, $context = null)
    {
        if ($arg1 instanceof DOMNode && !isset($context)) {
            foreach (self::$documents as $documentWrapper) {
                $compare = $arg1 instanceof DOMDocument ? $arg1 : $arg1->ownerDocument;
                if ($documentWrapper->document->isSameNode($compare)) {
                    $context = $documentWrapper->id;
                }
            }
        }
        if (!$context) {
            $domId = self::$defaultDocumentID;
            if (!$domId) {
                throw new Exception("Can't use last created DOM, because there isn't any. Use self::newDocument() first.");
            }
        } else if (is_object($context) && $context instanceof phpQueryObject) {
            $domId = $context->getDocumentID();
        } else if ($context instanceof DOMDocument) {
            $domId = self::getDocumentID($context);
            if (!$domId) {
                $domId = self::newDocument($context)->getDocumentID();
            }
        } else if ($context instanceof DOMNode) {
            $domId = self::getDocumentID($context);
            if (!$domId) {
                throw new Exception('Orphaned DOMNode');
//				$domId = self::newDocument($context->ownerDocument);
            }
        } else {
            $domId = $context;
        }
        if ($arg1 instanceof phpQueryObject) {
            if ($arg1->getDocumentID() == $domId) {
                return $arg1;
            }
            $class = get_class($arg1);
            // support inheritance by passing old object to overloaded constructor
            $phpQuery = $class != 'phpQuery' ? new $class($arg1, $domId) : new phpQueryObject($domId);
            $phpQuery->elements = [];
            foreach ($arg1->elements as $node) {
                $phpQuery->elements[] = $phpQuery->document->importNode($node, true);
            }
            return $phpQuery;
        } else if ($arg1 instanceof DOMNode || (is_array($arg1) && isset($arg1[0]) && $arg1[0] instanceof DOMNode)) {
            /*
             * Wrap DOM nodes with phpQuery object, import into document when needed:
             * pq(array($domNode1, $domNode2))
             */
            $phpQuery = new phpQueryObject($domId);
            if (!($arg1 instanceof DOMNodeList) && !is_array($arg1)) {
                $arg1 = array($arg1);
            }
            $phpQuery->elements = [];
            foreach ($arg1 as $node) {
                $sameDocument = $node->ownerDocument instanceof DOMDocument && !$node->ownerDocument->isSameNode($phpQuery->document);
                $phpQuery->elements[] = $sameDocument ? $phpQuery->document->importNode($node, true) : $node;
            }
            return $phpQuery;
        } else if (self::isMarkup($arg1)) {
            $phpQuery = new phpQueryObject($domId);
            return $phpQuery->newInstance($phpQuery->documentWrapper->import($arg1));
        } else {
            /**
             * Run CSS query:
             * pq('div.myClass')
             */
            $phpQuery = new phpQueryObject($domId);
            if ($context && $context instanceof phpQueryObject) {
                $phpQuery->elements = $context->elements;
            } else if ($context && $context instanceof DOMNodeList) {
                $phpQuery->elements = [];
                foreach ($context as $node) {
                    $phpQuery->elements[] = $node;
                }
            } else if ($context && $context instanceof DOMNode) {
                $phpQuery->elements = array($context);
            }
            return $phpQuery->find($arg1);
        }
    }

    /**
     * Checks if $input is HTML string, which has to start with '<'.
     * @param String $input
     * @return Bool
     * @todo still used ?
     */
    public static function isMarkup($input)
    {
        return !is_array($input) && substr(trim($input), 0, 1) == '<';
    }

    /**
     * Returns document with id $id or last used as phpQueryObject.
     * $id can be retrived via getDocumentID() or getDocumentIDRef().
     * Chainable.
     *
     * @see self::selectDocument()
     * @param unknown_type $id
     * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
     */
    public static function getDocument($id = null)
    {
        if ($id) {
            self::selectDocument($id);
        } else {
            $id = self::$defaultDocumentID;
        }
        return new phpQueryObject($id);
    }

    public static function DOMNodeListToArray($DOMNodeList)
    {
        $array = [];
        if (!$DOMNodeList) {
            return $array;
        }
        foreach ($DOMNodeList as $node) {
            $array[] = $node;
        }
        return $array;
    }

}