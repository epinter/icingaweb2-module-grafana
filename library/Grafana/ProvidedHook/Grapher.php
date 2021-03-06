<?php

namespace Icinga\Module\Grafana\ProvidedHook;

use Icinga\Application\Icinga;
use Icinga\Application\Config;
use Icinga\Exception\ConfigurationError;
use Exception;
use Icinga\Application\Hook\GrapherHook;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Web\Url;
use Icinga\Web\View;
use Icinga\Module\Grafana\Util;

class Grapher extends GrapherHook
{
    protected $config;
    protected $graphConfig;
    protected $auth;
    protected $cryptAlgo='aes-256-cbc';
    protected $grafana = array();
    protected $grafanaHost = null;
    protected $grafanaTheme = 'light';
    protected $protocol = "http";
    protected $usePublic = "no";
    protected $publicHost = null;
    protected $publicProtocol = "http";
    protected $timerange = "6h";
    protected $username = null;
    protected $password = null;
    protected $width = 640;
    protected $height = 280;
    protected $enableLink = true;
    protected $defaultDashboard = "icinga2-default";
    protected $hostDashboard = null;
    protected $defaultOrgId = "1";
    protected $shadows = false;
    protected $defaultDashboardStore = "db";
    protected $dataSource = null;
    protected $accessMode = "proxy";
    protected $timeout = "5";
    protected $refresh = "no";
    protected $title = "<h2>Performance Graph</h2>";
    protected $custvardisable = "grafana_graph_disable";
    protected $timeRanges = array(
        'Minutes' => array(
            '5m' => '5 minutes',
            '15m' => '15 minutes',
            '30m' => '30 minutes',
            '45m' => '45 minutes'
        ),
        'Hours' => array(
            '1h' => '1 hour',
            '3h' => '3 hours',
            '6h' => '6 hours',
            '8h' => '8 hours',
            '12h' => '12 hours',
            '24h' => '24 hours'
        ),
        'Days' => array (
            '2d' => '2 days',
            '7d' => '7 days',
            '14d' => '14 days',
            '30d' => '30 days',
        ),
        'Months' => array (
            '2M' => '2 month',
            '6M' => '6 months',
            '9M' => '9 months'
        ),
        'Years' => array(
            '1y' => '1 year',
            '2y' => '2 years',
            '3y' => '3 years'
        )
    );

    protected function init()
    {
        $this->config = Config::module('grafana')->getSection('grafana');
        $this->username = $this->config->get('username', $this->username);
        $this->grafanaHost = $this->config->get('host', $this->grafanaHost);
        $this->grafanaTheme = $this->config->get('theme', $this->grafanaTheme);
        if ($this->grafanaHost == null) {
            throw new ConfigurationError(
                'No Grafana host configured!'
            );
        }
        $this->password = $this->config->get('password', $this->password);
        $this->protocol = $this->config->get('protocol', $this->protocol);

        // Check if there is a timerange in url params
        $this->timerange = Url::fromRequest()->hasParam('timerange') ? Url::fromRequest()->getParam('timerange') : $this->config->get('timerange', $this->timerange);
        $this->timeout = $this->config->get('timeout', $this->timeout);
        $this->height = $this->config->get('height', $this->height);
        $this->width = $this->config->get('width', $this->width);
        $this->enableLink = $this->config->get('enableLink', $this->enableLink);
        if ( $this->enableLink == "yes" ) {
            $this->usePublic = $this->config->get('usepublic', $this->usePublic);
            if ( $this->usePublic == "yes" ) {
                $this->publicHost = $this->config->get('publichost', $this->publicHost);
                if ($this->publicHost == null) {
                    throw new ConfigurationError(
                        'No Grafana public host configured!'
                    );
                }
                $this->publicProtocol = $this->config->get('publicprotocol', $this->publicProtocol);
            } else {
                $this->publicHost = $this->grafanaHost;
                $this->publicProtocol = $this->protocol;
            }
        }
        $this->defaultDashboard = $this->config->get('defaultdashboard', $this->defaultDashboard);
        $this->defaultOrgId = $this->config->get('defaultorgid', $this->defaultOrgId);
        $this->shadows = $this->config->get('shadows', $this->shadows);
        $this->defaultDashboardStore = $this->config->get('defaultdashboardstore', $this->defaultDashboardStore);
        $this->dataSource = $this->config->get('datasource', $this->dataSource);
        $this->accessMode = $this->config->get('accessmode', $this->accessMode);
        $this->refresh = $this->config->get('directrefresh', $this->refresh);
        $this->refresh = ($this->refresh == "yes" && $this->accessMode == "direct" ? time() : 'now');
        $this->custvardisable = ($this->config->get('custvardisable', $this->custvardisable));
        if ($this->username != null) {
            if ($this->password != null) {
                $this->auth = $this->username . ":" . $this->password;
            } else {
                $this->auth = $this->username;
            }
        } else {
            $this->auth = "";
        }
        if ( Url::fromRequest()->hasParam('grafanaimage') ) {
            $imageUrl = Url::fromRequest()->getParam('grafanaimage');
            list($imageContent, $imageType, $imageSize) = $this->getGrafanaImage(rawurldecode($imageUrl));
            header('Content-Type: '.$imageType);
            header('Content-Length: '.$imageSize);
            echo $imageContent;
            exit;
        }
    }

    private function getGraphConf($serviceName, $serviceCommand, $hostgroups, $hostName)
    {
        $this->graphConfig = Config::module('grafana', 'graphs');

        if ($this->graphConfig->hasSection(strtok($serviceName, ' ')) && ($this->graphConfig->hasSection($serviceName) == False)) {
            $serviceName = strtok($serviceName, ' ');
        }
        if ($this->graphConfig->hasSection(strtok($serviceName, ' ')) == False && ($this->graphConfig->hasSection($serviceName) == False)) {
            $serviceName = $serviceCommand;
            if($this->graphConfig->hasSection($serviceCommand) == False && $this->defaultDashboard == 'none') {
                return NULL;
            }
        }

        $this->dashboard = $this->getGraphConfigOption($serviceName, 'dashboard', $this->defaultDashboard);
        $this->dashboardstore = $this->getGraphConfigOption($serviceName, 'dashboardstore', $this->defaultDashboardStore);
        $this->panelId = $this->getGraphConfigOption($serviceName, 'panelId', '1');
        $this->hostDashboard = $this->getGraphConfigOption($serviceName, 'hostDashboard');
        $this->orgId = $this->getGraphConfigOption($serviceName, 'orgId', $this->defaultOrgId);
        $this->customVars = $this->getGraphConfigOption($serviceName, 'customVars', '');
        $this->timerange = Url::fromRequest()->hasParam('timerange') ? Url::fromRequest()->getParam('timerange') : $this->getGraphConfigOption($serviceName, 'timerange', $this->timerange);
        $this->height = $this->getGraphConfigOption($serviceName, 'height', $this->height);
        $this->width = $this->getGraphConfigOption($serviceName, 'width', $this->width);

        foreach($hostgroups as $key => $value) {
            $this->dashboard=$this->getOverrideProperty($serviceName,'dashboard',$key,$this->dashboard);
            $this->dashboardstore=$this->getOverrideProperty($serviceName,'dashboardstore',$key,$this->dashboardstore);
            $this->panelId=$this->getOverrideProperty($serviceName,'panelId',$key,$this->panelId);
            $this->orgId=$this->getOverrideProperty($serviceName,'orgId',$key,$this->orgId);
            $this->hostDashboard=$this->getOverrideProperty($serviceName,'hostDashboard',$key,$this->hostDashboard);
            $this->customVars=$this->getOverrideProperty($serviceName,'customVars',$key,$this->customVars);
            $this->timerange=$this->getOverrideProperty($serviceName,'timerange',$key,$this->timerange);
            $this->height=$this->getOverrideProperty($serviceName,'height',$key,$this->height);
            $this->width=$this->getOverrideProperty($serviceName,'width',$key,$this->width);
        }
        $this->dashboard=$this->getOverrideProperty($serviceName,'dashboard',$hostName,$this->dashboard);
        $this->dashboardstore=$this->getOverrideProperty($serviceName,'dashboardstore',$hostName,$this->dashboardstore);
        $this->panelId=$this->getOverrideProperty($serviceName,'panelId',$hostName,$this->panelId);
        $this->orgId=$this->getOverrideProperty($serviceName,'orgId',$hostName,$this->orgId);
        $this->hostDashboard=$this->getOverrideProperty($serviceName,'hostDashboard',$hostName,$this->hostDashboard);
        $this->customVars=$this->getOverrideProperty($serviceName,'customVars',$hostName,$this->customVars);
        $this->timerange=$this->getOverrideProperty($serviceName,'timerange',$hostName,$this->timerange);
        $this->height=$this->getOverrideProperty($serviceName,'height',$hostName,$this->height);
        $this->width=$this->getOverrideProperty($serviceName,'width',$hostName,$this->width);

        if(!$this->dashboard || !$this->panelId) {
            return NULL;
        }

        return $this;
    }

    private function getGraphConfigOption($section, $option, $default=NULL) {
        $value = $this->graphConfig->get($section, $option, $default);
        if(empty($value)) {
            return $default;
        }
        return $value;
    }

    private function getOverrideProperty($serviceName,$property,$key,$default = null) {
        $ret=null;
        $overrides = $this->graphConfig->get($serviceName,$property.'Overrides');
        foreach(explode(PHP_EOL, $overrides) as $line) {
            preg_match("/\s*[\"']\s*([^\"']+)\s*['\"]\s*=\s*['\"]\s*([^'\"]+)\s*[\"']/", $line, $matches);
            if($matches) {
                #error_log($line.'======='.print_r($matches[1],1).'='.print_r($matches[2],1));
                if($key === $matches[1]) {
                    return $matches[2];
                }
            }
        }
        return $default;
    }

    private function getTimerangeLink($object, $rangeName, $timeRange)
    {
        $this->view = Icinga::app()->getViewRenderer()->view;
        if ($object instanceof Host) {
            $array = array(
                'host' => $object->host_name,
                'timerange' => $timeRange
            );
            $link = 'monitoring/host/show';
        } else {
            $array = array(
                'host' => $object->host->getName(),
                'service' => $object->service_description,
                'timerange' => $timeRange
            );
            $link = 'monitoring/service/show';
        }

        return $this->view->qlink(
            $rangeName,
            $link,
            $array,
            array(
                'class' => 'action-link',
                'data-base-target' => '_self',
                'title' => 'Set timerange for graph to ' . $rangeName
            )
        );
    }

    private function getGrafanaImage($q) {
        $url=sprintf("%s://%s/render/dashboard-solo/%s",
            $this->protocol,
            $this->grafanaHost,
            $this->decryptString($q)
        );
        $curl_handle = curl_init();
        $curl_opts = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERPWD => "$this->auth",
            CURLOPT_HTTPAUTH, CURLAUTH_ANY
        );
        curl_setopt_array($curl_handle, $curl_opts);
        $res = curl_exec($curl_handle);
        $info = curl_getinfo($curl_handle);
        $imagetype=$info['content_type'];
        $imagesize=$info['download_content_length'];

        if ($res === false || $info['http_code'] > 299) {
            $logmessage = array("Cannot fetch graph from server.");
            if ($res === false) {
                if(curl_errno($curl_handle) == 7) {
                    $logmessage[] = 'Failed to connect to host/port, Server unavailable.';
                } else {
                    $logmessage[] = curl_error($curl_handle);
                }
                error_log(sprintf("%s: %s",$logmessage[0],curl_error($curl_handle)));
            }
            if ($info['http_code'] > 299) {
                $logmessage[] = sprintf("Error: %s %s", $info['http_code'], Util::httpStatusCodeToString($info['http_code']));
                try {
                    $error = @json_decode($res);
                    $logmessage[] = (property_exists($error, 'message') ? $error->message : "");
                } catch(Exception $e) {
                    error_log("An exception occured while generating error message: ".$e->getMessage());
                }
                error_log(sprintf("'%s' %s (%s)",$logmessage[0],$logmessage[1],count($logmessage) >2?$logmessage[2]:''));
            }
            try {
                if (function_exists('gd_info')) {
                    $imagefont=3;
                    $marginx=20;
                    $imagefontwidth=imagefontwidth($imagefont);
                    $imagefontheight=imagefontheight($imagefont)+3;
                    $maxerrstr=max(array_map('strlen', $logmessage))*$imagefontwidth;
                    $im = imagecreatetruecolor($maxerrstr + (2*($imagefontwidth))+$marginx, ($imagefontheight)*(count($logmessage)+2));
                    $text_color = imagecolorallocate($im, 0, 0, 0);
                    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));

                    ob_start();
                    for($i=0;$i<count($logmessage);$i++) {
                        imagestring($im, $imagefont, $marginx, $imagefontheight+($imagefontheight*$i),  $logmessage[$i], $text_color);
                    }
                    imagepng($im,NULL,8);
                    $res = ob_get_contents();
                    ob_end_clean();

                    imagedestroy($im);
                    $imagetype='image/png';
                    $imagesize=strlen($res);
                } else {
                    error_log('WARNING: PHP-GD EXTENSION NOT AVAILABLE! (error messages wont be sent to browser)');
                }
            } catch(Exception $e) {
                error_log("An exception occured while generating error message: ".$e->getMessage());
            }

        }
        curl_close($curl_handle);

        return array($res, $imagetype, $imagesize);
    }

    //returns false on error, previewHTML is passed as reference
    private function getMyPreviewHtml($serviceName, $hostName, &$previewHtml, $object)
    {
        $imgClass = $this->shadows ? "grafana-img grafana-img-shadows" : "grafana-img";
        if ($this->accessMode == "proxy") {
            $pngUrl = sprintf(
                '%s/%s?var-hostname=%s&var-service=%s%s&panelId=%s&orgId=%s&width=%s&height=%s&theme=%s&from=now-%s&to=now',
                $this->dashboardstore,
                $this->dashboard,
                urlencode($hostName),
                rawurlencode($serviceName),
                $this->customVars,
                $this->panelId,
                $this->orgId,
                $this->width,
                $this->height,
                $this->grafanaTheme,
                $this->timerange
            );

            $this->view = Icinga::app()->getViewRenderer()->view;
            if ($object instanceof Host)
            {
                $array = array(
                      'host'       => $object->host_name,
                      'grafanaimage' => rawurlencode($this->encryptString($pngUrl)),
                );
                $link = 'monitoring/host/show';
            } else {
                $array = array(
                      'host'       => $object->host->getName(),
                      'service'    => $object->service_description,
                      'grafanaimage' => rawurlencode($this->encryptString($pngUrl)),
                );
                $link = 'monitoring/service/show';
            }
            $previewHtml = sprintf('<img src="%s" class="'. $imgClass .'">', $this->view->url($link, $array));
        } elseif ($this->accessMode == "direct") {
            $imghtml = '<img src="%s://%s/render/dashboard-solo/%s/%s?var-hostname=%s&var-service=%s%s&panelId=%s&orgId=%s&width=%s&height=%s&theme=%s&from=now-%s&to=now&trickrefresh=%s" alt="%s" width="%d" height="%d" class="'. $imgClass .'"/>';
            $previewHtml = sprintf(
                $imghtml,
                $this->protocol,
                $this->grafanaHost,
                $this->dashboardstore,
                $this->dashboard,
                urlencode($hostName),
                rawurlencode($serviceName),
                $this->customVars,
                $this->panelId,
                $this->orgId,
                $this->width,
                $this->height,
                $this->grafanaTheme,
                $this->timerange,
                $this->refresh,
                rawurlencode($serviceName),
                $this->width,
                $this->height
            );
        } elseif ($this->accessMode == "iframe") {
            $iframehtml = '<iframe src="%s://%s/dashboard-solo/%s/%s?var-hostname=%s&var-service=%s%s&panelId=%s&orgId=%s&theme=%s&from=now-%s&to=now" alt="%s" height="%d" frameBorder="0" style="width: 100%%;"></iframe>';
            $previewHtml = sprintf(
                $iframehtml,
                $this->protocol,
                $this->grafanaHost,
                $this->dashboardstore,
                $this->dashboard,
                urlencode($hostName),
                rawurlencode($serviceName),
                $this->customVars,
                $this->panelId,
                $this->orgId,
                $this->grafanaTheme,
                $this->timerange,
                rawurlencode($serviceName),
                $this->height
            );
        }
        return true;
    }

    public function has(MonitoredObject $object)
    {
        if (($object instanceof Host) || ($object instanceof Service)) {
            return true;
        } else {
            return false;
        }
    }

    public function getPreviewHtml(MonitoredObject $object)
    {
        // enable_perfdata = true ?  || disablevar == true
        if (!$object->process_perfdata || isset($object->customvars[$this->custvardisable])) {
            return '';
        }

        if ($object instanceof Host) {
            $serviceName = $object->check_command;
            $hostName = $object->host_name;
        } elseif ($object instanceof Service) {
            $serviceName = $object->service_description;
            $hostName = $object->host->getName();
        }

        if($this->getGraphConf($serviceName, $object->check_command, $object->hostgroups, $hostName) == NULL) {
            return;
        }

        if ($this->dataSource == "graphite") {
            $serviceName = preg_replace('/[^a-zA-Z0-9\*\-:]/', '_', $serviceName);
            $hostName = preg_replace('/[^a-zA-Z0-9\*\-:]/', '_', $hostName);
        }

        $customVars = $object->fetchCustomvars()->customvars;
        // replace template to customVars from Icinga2
        foreach ($customVars as $k => $v) {
            $search[] = "\$$k\$";
            $replace[] = is_string($v) ? rawurlencode($v)  : null;
            $this->customVars = str_replace($search, $replace, $this->customVars);
        }

        $return_html = "";
        $menu = '<table class="grafana-table"><tr>';
        $menu .= '<td><div class="grafana-icon"><div class="grafana-clock"></div></div></td>';
        foreach ($this->timeRanges as $key => $mainValue) {
            $menu .= '<td><ul class="grafana-menu-navigation"><a class="main" href="#">' . $key . '</a>';
            $counter = 1;
            foreach ($mainValue as $subkey => $value) {
                $menu .= '<li class="grafana-menu-n'. $counter .'">' . $this->getTimerangeLink($object, $value, $subkey) . '</li>';
                $counter++;
            }
            $menu .= '</ul></td>';
        }
        $menu .= '</tr></table>';

        foreach (explode(',', $this->panelId) as $panelid) {

            $html = "";
            $this->panelId = $panelid;

            //image value will be returned as reference
            $previewHtml = "";
            $res = $this->getMyPreviewHtml($serviceName, $hostName, $previewHtml, $object);

            //do not render URLs on error or if disabled
            if ($this->enableLink == "no") {
                $html .= $previewHtml;
            } else {
                $html .= '<a href="%s://%s/dashboard/%s/%s?var-hostname=%s%s&from=now-%s&to=now%s&orgId=%s';

                if(!$object instanceof Host && $this->hostDashboard) {
                    $this->hostDashboard = null;
                }
                if ($this->dashboard != $this->defaultDashboard && !$this->hostDashboard) {
                    $html .= '&panelId=' . $this->panelId . '&fullscreen';
                }

                $html .= '"target="_blank">%s</a>';

                $html = sprintf(
                    $html,
                    $this->publicProtocol,
                    $this->publicHost,
                    $this->dashboardstore,
                    $this->hostDashboard?$this->hostDashboard:$this->dashboard,
                    urlencode($hostName),
                    $this->customVars,
                    $this->timerange,
                    !$this->hostDashboard?sprintf("&var-service=%s",rawurlencode($serviceName)):'',
                    $this->orgId,
                    $previewHtml
                );

            }
            $return_html .= $html;
        }
        return '<div class="icinga-module module-grafana">'.$this->title.$menu.$return_html.'</div>';
    }

    private function genKey() {
        if(openssl_cipher_iv_length($this->cryptAlgo)==16) {
            $hash = hash('md5',$this->grafanaHost.$this->username.$this->password.$this->protocol.$this->dataSource);
        } else {
            $hash = hash('sha256',$this->grafanaHost.$this->username.$this->password.$this->protocol.$this->dataSource);
        }
        return pack("H*",$hash);
    }

    private function encryptString($string) {
        if (function_exists('openssl_encrypt')) {
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cryptAlgo));
            try {
                $encrypted = openssl_encrypt($string,$this->cryptAlgo,$this->genKey(),NULL,$iv);
            } catch(Exception $e) {
                error_log("openssl_encrypt failed: ".$e->getMessage());
            }
            return base64_encode(base64_encode($iv).':'.$encrypted);
        } else {
            error_log('WARNING: PHP-OPENSSL EXTENSION NOT AVAILABLE! (missing openssl_encrypt)');
            return base64_encode($string);
        }
    }

    private function decryptString($string) {
        if (function_exists('openssl_encrypt')) {
            $data = explode(':',base64_decode($string));
            $iv = base64_decode($data[0]);
            try {
                $decrypted = openssl_decrypt($data[1],$this->cryptAlgo,$this->genKey(),NULL,$iv);
            } catch(Exception $e) {
                error_log("openssl_decrypt failed: ".$e->getMessage());
            }
            return rtrim($decrypted,"\0");
        } else {
            return base64_decode($string);
        }
    }
}
