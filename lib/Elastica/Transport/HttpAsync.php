<?php
namespace Elastica\Transport;

use Elastica\{
    Connection,
    Request,
    Response,
    Util
};

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
        $command_params[] = ' --max-time ' . $connection->getTimeout() . ' --connect-timeout ' . $connection->getTimeout();
        $data = $request->getData();

        $headers = $this->_buildHeaders($request);

        foreach ($headers as $header) {
            $command_params[] = ' -H ' . '"' . $header . '"';
        }

        if (!empty($data) || '0' === $data) {
            $content = str_replace('\/', '/', $data);
            /**
             * The reason of setting this 2 lines below in that format, is that Elasticsearch is expecting
             * a real new line, so not \n, \n\r or PHP_EOL will work, just real line.
             */
            $command_params[] = ' --data ' . "'$content
'";
        }

        $command_params_string = implode(' ', $command_params);
        $curl_command = sprintf('nohup %s %s %s &>/dev/null', $command, $command_params_string, $baseUri);

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
    protected function _buildBaseUri(Request $request, Connection $connection): string
    {
        $baseUri = $connection->hasConfig('url') ? $connection->getConfig('url') : '';

        if (empty($url)) {
            $baseUri = sprintf('%s://%s:%d/%s', $this->_scheme, $connection->getHost(), $connection->getPort(), $connection->getPath());
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

    /**
     * @param Request $request
     *
     * @return array
     */
    protected function _buildHeaders(Request $request): array
    {
        return [
            sprintf('Content-Type: %s', $request->getContentType()),
        ];
    }
}
