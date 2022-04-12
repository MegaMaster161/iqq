<?php
include "vendor/autoload.php";

use \GuzzleHttp\Client;
use \GuzzleHttp\Psr7\Request;
use \GuzzleHttp\Cookie\CookieJar as CookieHandler;
use \GuzzleHttp\Command\Guzzle\GuzzleClient;




class AdapterIQQ {

    private $client;
    private $url_base = '';
    private $login_info = [
                    'username' => '',
                    'passwd' => ''
                ];


    private $urls_path = [
        'filter' => '/path/to/action',
        'get' => '/path/to/action',
        'filter_group' => '/path/to/action',
    ];

    private $state = [];

    private $flow_token;
    private $uaid;
 
//    private $client_id = 'e37ffdec11c0245cb2e0';
 

    public function __construct()
    {
        $this->client = new Client(['cookies' => true]);
        $this->get_landing_context_azure();
        // $this->send_username_info();
        // $this->do_auth_in_azure();
    }

    public function get_landing_context_azure()
    {
        $client = $this->client;
        $headers_init = [
            'Connection' => 'keep-alive',
            'Host'=> 'iqq.abbott.com',
            'User-Agent'=> 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.119 Safari/537.36'
         ];
     
        $landing = $client->request('GET','https://office.com/login', [/*'headers' => $headers_init,*/ 
                                                                        'debug'=> true,
                                                                        'allow_redirects' => ['track_redirects' => true]]);
            $raw_html = $landing->getBody()->getContents();
            $cookie_jar = $client->getConfig('cookies');
            $cookie = $cookie_jar->toArray();
            $headers = $landing->getHeaders();
            $historyUri = $landing->getHeader(\GuzzleHttp\RedirectMiddleware::HISTORY_HEADER);
            
            $data = [
                'flowToken' => $this->search_in_serverData('sFT', $raw_html),
                'ctx' => $this->search_in_serverData('sCtx', $raw_html),
                'requestId' => $this->search_in_serverData('requestId', $raw_html),
                'apiCanary' => $this->search_in_serverData('apiCanaty', $raw_html),
                'canary' => $this->search_in_serverData('canary', $raw_html),
                'correlationId' => $this->search_in_serverData('correlationId', $raw_html),
                'x-ms-request-id' => $headers['x-ms-request-id'][0]
            ];
           // var_dump($data);
            var_dump($this->parse_uri($historyUri[1]));
        }
        
        
    private function parse_uri($link_microsoftonline){
        return parse_url($link_microsoftonline);
    }   
    

    private function search_in_serverData($name, $data)
    {

        $regExp = '/"'.$name.'":"(.*?)"/';
            preg_match($regExp, $data, $mathes);
        return $mathes[1];

    }

    public function send_username_info()
    {
        $client = $this->client;

        $headers_for_login = [
            'Host' => 'login.live.com',
            'Accept' => 'application/json',
            'hpgact' => '0',
            'Content-Type' => 'application/json; charset=UTF-8',
            'client-request-id' => $this->uaid,
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36'    
        ];

        
        $body_request = [
            'checkPhones' => false,
            'flowToken' => $this->flow_token,
            'isCookieBannerShown' => false,
            'isFidoSupported' => false,
            'isOtherIdPSupported' => false,
            'uaia' => $this->uaid,
            'username' => $this->login_info['username'],
        ];

        $cookie_jar = $client->getConfig('cookies');

        $send_login = $client->request('POST', 'https://login.live.com/GetCredentialType.srf',
                                        ['headers' => $headers_for_login,
                                         'json' => $body_request,
                                         'cookies' => $cookie_jar] );

     //   $response_login = $send_login->getBody()->getContents();
    //     var_dump($this->flow_token);
    //     var_dump($client->getConfig('cookies'));
        
    //    var_dump($response_login);

    }

    public function do_auth_in_azure()
    {
        $client = $this->client;

        $headers_for_auth = [
            'Host' => 'login.live.com',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'hpgact' => '0',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'client-request-id' => $this->uaid,
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36'    
        ];

        $json = [
            'i13' => '0',
            'login' => $this->login_info['username'],
            'loginfmt' => $this->login_info['username'],
            'type'=> '11',
            'LoginOptions' => '3',
            'lrt' => '',
            'lrtPartition' =>'',
            'hisRegion' => '',
            'hisScaleUnit' => '',
            'passwd' => $this->login_info['passwd'],
            'ps' => '',
            'psRNGCDefaultType'=> '',
            'psRNGCEntropy' => '',
            'psRNGCSLK' => '',
            'canary' => '',
            'ctx' => '',
            'hpgrequestid' => '',
            'PPFT' => $this->flow_token,
            'PPSX' => 'P',
            'NewUser' => '1',
            'FoundMSAs' => '',
            'fspost' =>'0',
            'i21' => '0',
            'CookieDisclosure' => '0',
            'IsFidoSupported' => '1',
            'isSignupPost' => '0',
            'i19' => '3380'
        ];
        $prepare_cookie = $client->getConfig('cookies');
        $request_auth = $client->request('POST', 'https://login.live.com/ppsecure/post.srf', 
            ['headers' => $headers_for_auth,
             'form_params'=> $json , 
             'cookies' => $prepare_cookie, 
             'debug' => true]);

        $response_auth = $request_auth->getBody()->getContents();
        $cookie_jar = $client->getConfig('cookies');
        var_dump($cookie_jar);

    }


    // public function do_request_to_iqq_service($path, $data)
    // {
      
    // }

}

$adapter = new AdapterIQQ();


?>