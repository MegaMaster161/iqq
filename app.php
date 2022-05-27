<?php
include "vendor/autoload.php";

use \GuzzleHttp\Client;
use \GuzzleHttp\Psr7\Request;
use \GuzzleHttp\Cookie\CookieJar as CookieHandler;
use \GuzzleHttp\Command\Guzzle\GuzzleClient;
use Exception;

date_default_timezone_set('Europe/Berlin');
ini_set('display_errors', 0);


class AdapterIQQ {

    private $client;
    private $url_base = '';
    private $login_info = [
                    'username' => '',
                    'passwd' => ''
                ];


                private $urls_path = [
                    'auth' => 'identityiq/home.jsf',// document - get
                    'setTimeZone' => 'identityiq/ui/setTimeZone.jsf',// document - get
                    'access' => '/identityiq/accessRequest/accessRequest.jsf', // document - get CSRF & XSRF parse
                    'filter' => '/path/to/action', //
                    'get' => '/path/to/action',
                    'filter_group' => '/path/to/action',
                ];
            
                private $data = [];
            
                private $SAMLResponse;
                
                private $CSRF;
                private $XSRF;
             
                public function __construct()
                {
                    $this->client = new Client(['cookies' => true]);
                   # $this->client->setDefaultOptions('verify', false);
                    $this->init_process_login();
                }
            
                private function init_process_login()
                {
                    $this->get_landing_context_azure();
                    $this->send_username_info();
                    $this->do_auth_in_azure();
            
                    $json = [
                        'SAMLResponse' => $this->SAMLResponse,
                        'RelayState' => $this->url_base.$this->urls_path['auth']
                    ];
            
                    $this->get_session_iiq_service('POST', $json );
            
                    $this->get_session_iiq_service('GET', false);
                }
            
                public function get_landing_context_azure()
                {
                    $client = $this->client;
                    $headers_init = [
                        'Connection' => 'keep-alive',
                        'Host'=> 'iqq.abbott.com',
                        'User-Agent'=> 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.119 Safari/537.36'
                     ];
                 
                    $landing = $client->request('GET', $this->url_base, [/*'headers' => $headers_init,*/ 
                                                                                    'debug'=> false,
                                                                                    'verify' => false,
                                                                                    'allow_redirects' => ['track_redirects' => true]]);
                        $raw_html = $landing->getBody()->getContents();
                        $cookie_jar = $client->getConfig('cookies');
                        $headers = $landing->getHeaders();
                        $historyUri = $landing->getHeader(\GuzzleHttp\RedirectMiddleware::HISTORY_HEADER);
                        
                        $data = [
                            'flowToken' => $this->search_in_serverData('sFT', $raw_html),
                            'ctx' => $this->search_in_serverData('sCtx', $raw_html),
                            'requestId' =>null /*$this->search_in_serverData('requestId', $raw_html)*/,
                            'apiCanary' => $this->search_in_serverData('apiCanary', $raw_html),
                            'canary' => $this->search_in_serverData('canary', $raw_html),
                            'correlationId' => $this->search_in_serverData('correlationId', $raw_html),
                            'x-ms-request-id' => $headers['x-ms-request-id'][0],
                            'client-request-id' => $this->search_in_serverData('correlationId', $raw_html),
                            'hpgrequestid' => $this->search_in_serverData('sessionId', $raw_html),
                            'Referer' => $historyUri[1]
                        ];
            
                        $this->data = $data;
                        // var_dump($data);
            
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
                        'Accept' => 'application/json',
                        'canary' => $this->data['canary'],
                        'Referer' => $this->data['Referer'],
                        'Origin' => 'https://login.microsoftonline.com',
                        'client-request-id' => $this->data['client-request-id'],
                        'hpgact' => '1900',
                        'hpgid' => '1104',
                        'Host' => 'login.microsoftonline.com',
                        'hpgrequestid' => $this->data['hpgrequestid'],
                        'Content-Type' => 'application/json; charset=UTF-8',
                        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36'    
                    ];
            
                    
                    $body_request = [
                        'checkPhones' => false,
                        'country' => 'DE',
                        'federationFlags' => 0,
                        'flowToken' => $this->data['flowToken'],
                        'forceotclogin' => false,
                        'isAccessPassSupported' => true,
                        'isCookieBannerShown' => false,
                        'isExternalFederationDisallowed' => false,
                        'isFidoSupported' => false,
                        'isOtherIdpSupported' => true,
                        'isRemoteConnectSupported' => false,
                        'isRemoteNGCSupported' => true,
                        'isSignup' => false,
                        'originalRequest' => $this->data['ctx'],
                        'username' => $this->login_info['username'],
                    ];
            
                    $cookie_jar = $client->getConfig('cookies');
            
                    $send_login = $client->request('POST', 'https://login.microsoftonline.com/common/GetCredentialType?mkt=en-US',
                                                    ['headers' => $headers_for_login,
                                                     'json' => $body_request,
                                                     'cookies' => $cookie_jar] );
            
                    
                }
            
                public function do_auth_in_azure()
                {
                    $client = $this->client;
            
            
                    $headers_for_auth = [
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                        'Referer' => $this->data['Referer'],
                        'Origin' => 'https://login.microsoftonline.com',
                        'client-request-id' => $this->data['client-request-id'],
                        'hpgact' => '1900',
                        'hpgid' => '1104',
                        'Host' => 'login.microsoftonline.com',
                        'hpgrequestid' => $this->data['hpgrequestid'],
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'Content-Length' => '1531',
                        'sec-ch-ua' => '" Not A;Brand";v="99", "Chromium";v="100", "Google Chrome";v="100"',
                        'sec-ch-ua-platform' => '"Windows"',
                        'sec-ch-ua-mobile' => '?0',
                        'Sec-Fetch-Dest' => 'document',
                        'Sec-Fetch-Mode' => 'navigate',
                        'Sec-Fetch-Site' => 'same-origin',
                        'Sec-Fetch-User' => '?1',
                        'Upgrade-Insecure-Requests' => '1',
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
                        'ps' => '2',
                        'psRNGCDefaultType'=> '',
                        'psRNGCEntropy' => '',
                        'psRNGCSLK' => '',
                        'canary' => $this->data['canary'],
                        'ctx' => $this->data['ctx'],
                        'hpgrequestid' => $this->data['hpgrequestid'],
                        'flowToken' => $this->data['flowToken'],
                        'PPSX' => 'P',
                        'NewUser' => '1',
                        'FoundMSAs' => '',
                        'fspost' =>'0',
                        'i21' => '0',
                        'CookieDisclosure' => '0',
                        'IsFidoSupported' => '1',
                        'isSignupPost' => '0',
                      //  'i19' => '35114'
                    ];
            
            
                    $prepare_cookie = $client->getConfig('cookies');
            
                    $request_auth = $client->request('POST', $this->get_login_uri($this->data['Referer']), 
                        [/* 'headers' => $headers_for_auth, */
                         'form_params'=> $json , 
                         'cookies' => $prepare_cookie, 
                         'debug' => false]);
            
                    $historyUri = $request_auth->getHeader(\GuzzleHttp\RedirectMiddleware::HISTORY_HEADER);
            
                    $response_auth = $request_auth->getBody()->getContents();
                    $this->parse_SALMResponse($response_auth);
                    #$cookie_jar = $client->getConfig('cookies');
                    
            
                }
            
                private function parse_SALMResponse($raw_html)
                {
                    $regExpFlowToken = '#name="SAMLResponse" value="("|)([^"]+)"#';
                    preg_match($regExpFlowToken, $raw_html, $saml_matches);
                    $this->SAMLResponse = $saml_matches[2];
                }
            
                private function get_login_uri($referer)
                {
                    $parse_uri_referer = parse_url($referer);
                    $parse = explode('/', $parse_uri_referer['path']);
                    return 'https://login.microsoftonline.com/'.$parse[1].'/login';
                }
            
            
                private function get_session_iiq_service($method, $json)
                {
                    $client = $this->client;
                    //TODO поправить заголовки по феншую. 
                    $headers_for_session_iiq = [
                        'Accept' => 'application/json',
                        'Referer' => $this->data['Referer'],
                        'Origin' => 'https://login.microsoftonline.com',
                        'client-request-id' => $this->data['client-request-id'],
                        'hpgact' => '1900',
                        'hpgid' => '1104',
                        'Host' => 'login.microsoftonline.com',
                        'hpgrequestid' => $this->data['hpgrequestid'],
                        'Content-Type' => 'application/json; charset=UTF-8',
                        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36'    
                    ];
                    $cookie_jar = $client->getConfig('cookies');
                    // $json = [
                    //     'SAMLResponse' => $this->SAMLResponse,
                    //     'RelayState' => $this->url_base.$this->urls_path['auth']
                    // ];
                    $params =  [
                        /*'headers' => $headers_for_session_iiq,*/ 
                        'debug'=> false,
                        'verify' => false,
                        'cookie' => $cookie_jar,
                    /*'allow_redirects' => false*/
                    ];
            
                    if($json){
                        $params = array_merge($params, ['form_params' => $json]);
                    }
            
                    $init_session = $client->request($method, $this->url_base.$this->urls_path['auth'],$params);
                    
                   // $historyUri = $init_session->getHeader(\GuzzleHttp\RedirectMiddleware::HISTORY_HEADER);
                    
                    if(!$json){
                        var_dump($init_session->getBody()->getContents());
                      //  var_dump($client->getConfig('cookies'));
                    }
                    
            
                }
            
                private function get_csrf_token($raw_html)
                {
                    $regExp = "/'CSRF-TOKEN', '(.*?)'/";
                        preg_match($regExp, $raw_html, $mathes);
                    return $mathes[1];
                }

                private function get_xsrf_token($raw_html)
                {
                    $regExp = "/SailPoint.XSRF_TOKEN = '(.*?)';/";
                    preg_match($regExp, $raw_html, $mathes);
                    return $mathes[1];
                }
            
                public function get_user($user_id)
                {
                    $client = $this->client;
                    //TODO поправить заголовки по феншую. 
                    $headers_for_session_iiq = [
                        'Accept' => 'application/json',
                        'Referer' => $this->data['Referer'],
                        'Origin' => 'https://login.microsoftonline.com',
                        'client-request-id' => $this->data['client-request-id'],
                        'hpgact' => '1900',
                        'hpgid' => '1104',
                        'Host' => 'login.microsoftonline.com',
                        'hpgrequestid' => $this->data['hpgrequestid'],
                        'Content-Type' => 'application/json; charset=UTF-8',
                        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36'    
                    ];
            
                    $cookie_jar = $client->getConfig('cookies');
            
                    $json = [
                        'SAMLResponse' => $this->SAMLResponse,
                        'RelayState' => $this->url_base.$this->urls_path['auth']
                    ];
            
            
                    $request = $client->request('POST', $this->url_base, ['headers' => $headers_for_session_iiq, 
                                                                                'debug'=> false,
                                                                                'verify' => false,
                                                                                'cookie' => $cookie_jar,
                                                                                'allow_redirects' => ['track_redirects' => true]]);
                    
                    
                    $result = $request->getBody()->getContents();
            
                    return $result;
                }
            
            
            
            
            }
            
            $adapter = new AdapterIQQ();
            


?>