<?php
namespace Elastica\Transport;

use Elastica\Request;
use Elastica\Response;
use Elastica\Util;

/**
 * Elastica HttpAsync Transport object.
 *
 * @author Norberto Gomez
 */
class HttpAsync extends AbstractTransport
{
    /**
     * Http scheme.
     *
     * @var string Http scheme
     */
    protected $_scheme = 'http';

    /**
     * Curl resource to reuse.
     *
     * @var resource Curl resource to reuse
     */
    protected static $_curlConnection;

    /**
     * Makes calls to the elasticsearch server.
     *
     * All calls that are made to the server are done through this function
     *
     * @param \Elastica\Request $request
     * @param array             $params  Host, Port, ...
     *
     * @return \Elastica\Response Response object
     */
    public function exec(Request $request, array $params)
    {
        $connection = $this->getConnection();
        $baseUri = $this->_buildBaseUri($request, $connection);
        $command = 'curl';
        $command_params = [];
        $command_params[] = ' -m ' . $connection->getTimeout();
        $data = $request->getData();

        $headers = [];
        array_push($headers, sprintf('Content-Type: %s', $request->getContentType()));

        foreach ($headers as $header) {
            $command_params[] = ' -H ' . '"' . $header . '"';
        }

        if (!empty($data) || '0' === $data) {
            $content = str_replace('\/', '/', $data);
            $command_params[] = ' --data ' . "'$content
'";
        }

        $curl_command = 'nohup ' . $command . implode(' ', $command_params) . ' ' . $baseUri . ' > /dev/null';

        exec($curl_command);
        $response = new Response('Done', 200);

        return $response;
    }

    /**
     * @param Request $request
     * @param         $connection
     *
     * @return string
     */
    protected function _buildBaseUri(Request $request, $connection): string
    {
        $url = $connection->hasConfig('url') ? $connection->getConfig('url') : '';

        if (!empty($url)) {
            $baseUri = $url;
        } else {
            $baseUri = $this->_scheme . '://' . $connection->getHost() . ':' . $connection->getPort() . '/' . $connection->getPath();
        }

        $requestPath = $request->getPath();

        if (!Util::isDateMathEscaped($requestPath)) {
            $requestPath = Util::escapeDateMath($requestPath);
        }

        $baseUri .= $requestPath;

        $query = $request->getQuery();

        if (!empty($query)) {
            $baseUri .= '?' . http_build_query(
                $this->sanityzeQueryStringBool($query)
            );
        }

        return $baseUri;
    }
}
