<?php // vim:set ts=4 sw=4 et:

namespace System\Library\ElasticSearch\Transport;

use \Memcache;
use System\Library\ElasticSearch\DSL\Stringify;

/**
 * This file is part of the ElasticSearch PHP client
 *
 * (c) Raymond Julin <raymond.julin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Memcached extends Base {
    public function __construct($host="127.0.0.1", $port=11311, $timeout=null) {
        parent::__construct($host, $port);
        $this->conn = new Memcache;
        $this->conn->connect($host, $port, $timeout);
    }

    /**
     * Index a new document or update it if existing
     *
     * @return array
     * @param array $document
     * @param mixed $id Optional
     * @param array $options
     * @throws \ElasticSearch\Exception
     */
    public function index($document, $id=false, array $options = array()) {
        if ($id === false)
            throw new \ElasticSearch\Exception("Memcached transport requires id when indexing");

        $document = json_encode($document);
        $url = $this->buildUrl(array($this->type, $id));
        $response = $this->conn->set($url, $document);
        return array(
            'ok' => $response
        );
    }

    /**
     * Update a part of a document
     *
     * @return array
     * @param  array                    $partialDocument
     * @param  mixed                    $id
     * @param  array                    $options
     */
    public function update($partialDocument, $id, array $options = array()) {
        $document = json_encode(array('doc' => $partialDocument));
        $url = $this->buildUrl(array($this->type, $id));
        $response = $this->conn->set($url, $document);

        return array(
            'ok' => $response,
        );
    }

    /**
     * Search
     *
     * @return array
     * @param array|string $query
     * @throws \ElasticSearch\Exception
     */
    public function search($query) {
        if (is_array($query)) {
            if (array_key_exists("query", $query)) {
                $dsl = new Stringify($query);
                $q = (string) $dsl;
                $url = $this->buildUrl(array(
                    $this->type, "_search?q=" . $q
                ));
                $result = json_decode($this->conn->get($url), true);
                return $result;
            }
            throw new \ElasticSearch\Exception("Memcached protocol doesnt support the full DSL, only query");
        }
        elseif (is_string($query)) {
            /**
             * String based search means http query string search
             */
            $url = $this->buildUrl(array(
                $this->type, "_search?q=" . $query
            ));
            $result = json_decode($this->conn->get($url), true);
            return $result;
        }
    }

    /**
     * Perform a request against the given path/method/payload combination
     * Example:
     * $es->request('/_status');
     *
     * @param string|array $path
     * @param string $method
     * @param array|bool $payload
     * @return array
     */
    public function request($path, $method="GET", $payload=false) {
        $url = $this->buildUrl($path);
        switch ($method) {
            case 'GET':
                $result = $this->conn->get($url);
                break;
            case 'DELETE':
                $result = $this->conn->delete($url);
                break;
        }
        return json_decode($result);
    }

    /**
     * Flush this index/type combination
     *
     * @return array
     * @param mixed $id
     * @param array $options Parameters to pass to delete action
     */
    public function delete($id=false, array $options = array()) {
        if ($id)
            return $this->request(array($this->type, $id), "DELETE");
        else
            return $this->request(false, "DELETE");
    }
}
