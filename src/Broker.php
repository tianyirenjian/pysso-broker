<?php
namespace Goenitz\PySso;


use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

class Broker
{
    protected $server_host;
    protected $server_port;
    protected $server_ssl;

    private $attributes = [];
    private $sid = null;
    public function __construct($server_host, $server_ssl = false, $server_port = null)
    {
        $this->server_host = $server_host;
        $this->server_port = $server_port;
        $this->server_ssl = $server_ssl;
    }

    public function setSlo($data)
    {
        $client = new Client([
            'cookies' => CookieJar::fromArray([
                'session' => $this->sid
            ], $this->server_host),
            'http_errors' => false
        ]);
        $promise =  $client->postAsync($this->SloUrl(), [
            'form_params' => array_merge($data, [
                'url' => $this->getCurrentUrl()
            ])
        ]);
        $promise->wait();
    }

    public function login()
    {
        // 可能引起无限重定向
        if (isset($_GET['st'])) {
            $ticket = $_GET['st'];
            $ticket_array = explode('/', $ticket);
            if (count($ticket_array) != 2) {
                return redirect($this->serverUrl() . '?service=' . $this->getCurrentUrl());
            }
            $this->sid = $ticket_array[1];
            $client = new Client([
                'headers' => ['Referer' => $this->getCurrentUrl()],
                'cookies' => CookieJar::fromArray([
                    'session' => $this->sid
                ], $this->server_host),
                'http_errors' => false
            ]);
            $response = $client->get($this->validateUrl() . '?ticket=' . $ticket);
            if ($response->getStatusCode() == 200) {
                $this->attributes = json_decode($response->getBody()->getContents());
                return true;
            } else {
                return redirect($this->serverUrl() . '?service=' . $this->getCurrentUrl());
            }
        } else {
            return redirect($this->serverUrl() . '?service=' . $this->getCurrentUrl());
        }
    }

    public function logout($service = null)
    {
        if ($service) {
            return redirect($this->LogoutUrl() . '?service=' . $service);
        }
        return redirect($this->LogoutUrl());
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    private function serverUrl()
    {
        return ($this->server_ssl ? 'https://' : 'http://') . $this->server_host
            . ($this->server_port ? ':' . $this->server_port : '') . '/login';
    }

    private function validateUrl()
    {
        return ($this->server_ssl ? 'https://' : 'http://') . $this->server_host
            . ($this->server_port ? ':' . $this->server_port : '') . '/validate';
    }

    private function SloUrl()
    {
        return ($this->server_ssl ? 'https://' : 'http://') . $this->server_host
            . ($this->server_port ? ':' . $this->server_port : '') . '/set-slo';
    }

    private function LogoutUrl()
    {
        return ($this->server_ssl ? 'https://' : 'http://') . $this->server_host
            . ($this->server_port ? ':' . $this->server_port : '') . '/logout';
    }

    private function getCurrentUrl()
    {
        if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on'){
            $current_url='https://' . $_SERVER['SERVER_NAME'];
            if ($_SERVER['SERVER_PORT'] != '443') {
                $current_url .= ':' . $_SERVER['SERVER_PORT'];
            }
        } else {
            $current_url='http://' . $_SERVER['SERVER_NAME'];
            if ($_SERVER['SERVER_PORT'] != '80') {
                $current_url .= ':' . $_SERVER['SERVER_PORT'];
            }
        }
        $current_url .= $_SERVER['REQUEST_URI'];
        return $current_url;
    }
}
