<?php
/**
* @method mixed CMD_API_POP(string $action, string $domain, string $user, string $passwd, string $passwd2, int $quota, string $limit)
*/
class DirectAdminSignAPI
{
    private $da ;
    private $arg_names = array(
    'CMD_API_POP' => array( 'action', 'domain', 'user', 'passwd', 'passwd2', 'quota', 'limit' ),
    ) ;

    /**
     * @brief Constructor
     * @param $url URL to DirectAdmin host
     *
     */
    public function __construct($url)
    {
        $this->da = parse_url($url);

        if (! $this->da['port']) {
            $this->da['port'] = ($this->da['scheme'] == 'https') ? 443 : 80 ;
        }
    }

    public function __call($name, $arguments)
    {
        $data = $this->make_command_data($name, $arguments) ;
        return $this->DoCommand(array( 'method' => 'POST', 'command' => $name, 'data' => $data )) ;
    }

    private function make_command_data($name, $arguments)
    {
        if (! isset($this->arg_names[ $name ])) {
            throw new Exception('Invalid DirectAdmin command') ;
        }

        $names = &$this->arg_names[ $name ] ;

        if (count($names) != count($arguments)) {
            throw new Exception('Invalid arguments of DirectAdmin command ' . $name) ;
        }

        $transformed = array() ;
        foreach ($names as $index => $value) {
            $transformed[$value] = $arguments[$index] ;
        }

        return $transformed ;
    }

    private function DoCommand($argument)
    {
        if (! is_array($argument) || ! count($argument)) {
            return null ;
        }

        $command = $argument['command'] ;
        if (empty($command) || ! is_string($command)) {
            return null ;
        }

        switch (strcasecmp($argument['method'], 'POST')) {
            case 0:
                $post = 1;
                $method = 'POST';
                break;
            default:
                $post = 0;
                $method = 'GET';
        }

        $content_length = 0 ;
        $data = '';
        if (is_array($argument['data']) && count($argument['data'])) {
            $pair = '' ;
            foreach ($argument['data'] as $index => $value) {
                $pair .= $index . '=' . urlencode($value) . '&' ;
            }

            $data = rtrim($pair, '&') ;
            $content_length = ($post) ? strlen($data) : 0 ;
        }

        $prefix = ($this->da['scheme'] == 'https') ? 'ssl://' : null;
        $error = array();
        $fp = @fsockopen($prefix . $this->da['host'], $this->da['port'], $error['number'], $error['string'], 10) ;
        if (! $fp) {
            return null ;
        }

        $http_header = array(
            $method . ' /' . $command . ((!$post) ? '?' . $data : '') . ' HTTP/1.0',
            'Authorization: Basic ' . base64_encode($this->da['user'] . ':' . $this->da['pass']),
            'Host: ' . $this->da['host'],
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . $content_length,
            'Connection: close'
        ) ;

        $request = implode("\r\n", $http_header) . "\r\n\r\n" ;
        fwrite($fp, $request . (($post) ? $data : '')) ;

        $returned = '' ;
        while ($line = @fread($fp, 1024)) {
            $returned .= $line;
        }

        fclose($fp);

        $h = strpos($returned, "\r\n\r\n");
        $head['all'] = substr($returned, 0, $h);
        $head['part'] = explode("\r\n", $head['all']);

        $body = substr($returned, $h + 4); # \r\n\r\n = 4

        return rtrim((string) $body);
    }
}
