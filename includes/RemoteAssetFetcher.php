<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class RemoteAssetFetcher
{
    private$transport;private$resolver;
    public function __construct(?callable$transport=null,?callable$resolver=null)
    {
        $this->transport=$transport??static fn(string$url,array$args):mixed=>wp_safe_remote_get($url,$args);
        $this->resolver=$resolver??static function(string$host):array{$ips=[];if(filter_var($host,FILTER_VALIDATE_IP))return[$host];$records=function_exists('dns_get_record')?@dns_get_record($host,DNS_A|DNS_AAAA):false;foreach(is_array($records)?$records:[]as$record){if(!empty($record['ip']))$ips[]=$record['ip'];if(!empty($record['ipv6']))$ips[]=$record['ipv6'];}if(!$ips)foreach(@gethostbynamel($host)?:[]as$ip)$ips[]=$ip;return array_values(array_unique($ips));};
    }

    /** @return array{body:string,content_type:string,final_url:string} */
    public function fetch(string$url,array$allowedDomains,int$maxBytes,array$acceptedTypes):array
    {
        try{$current=SourceUrl::canonicalize($url);}catch(\Throwable$e){throw new RemoteAssetException('url_invalid',$e);}
        $initialHost=SourceUrl::normalizeHost((string)(wp_parse_url($current)['host']??''));$allowed=array_values(array_unique(array_filter(array_merge([$initialHost],array_map([SourceUrl::class,'normalizeHost'],$allowedDomains)))));
        for($hop=0;$hop<=3;$hop++){
            $host=SourceUrl::normalizeHost((string)(wp_parse_url($current)['host']??''));if($host===''||!Security::validateFeedUrl($current,$allowed))throw new RemoteAssetException('host_invalid');$before=$this->resolvePublic($host);
            $response=($this->transport)($current,['timeout'=>15,'redirection'=>0,'reject_unsafe_urls'=>true,'limit_response_size'=>$maxBytes+1,'user-agent'=>'WordPress-News-Bot/'.(defined('WPNB_VERSION')?WPNB_VERSION:'dev'),'headers'=>['Accept'=>implode(', ',$acceptedTypes)]]);
            if(is_wp_error($response))throw new RemoteAssetException('http_failed');$after=$this->resolvePublic($host);if(!array_intersect($before,$after))throw new RemoteAssetException('dns_failed');$status=(int)wp_remote_retrieve_response_code($response);
            if(in_array($status,[301,302,303,307,308],true)){$location=trim((string)wp_remote_retrieve_header($response,'location'));if($location===''||$hop===3)throw new RemoteAssetException('redirect_blocked');try{$current=$this->redirect($current,$location);}catch(\Throwable$e){throw new RemoteAssetException('redirect_blocked',$e);}if(!Security::validateFeedUrl($current,$allowed))throw new RemoteAssetException('redirect_blocked');continue;}
            if($status<200||$status>=300)throw new RemoteAssetException('http_status_invalid');$length=(int)wp_remote_retrieve_header($response,'content-length');if($length>$maxBytes)throw new RemoteAssetException('body_too_large');$body=(string)wp_remote_retrieve_body($response);if($body===''||strlen($body)>$maxBytes)throw new RemoteAssetException($body===''?'body_empty':'body_too_large');$type=strtolower(trim(explode(';',(string)wp_remote_retrieve_header($response,'content-type'))[0]));if($acceptedTypes&&!in_array($type,$acceptedTypes,true))throw new RemoteAssetException('content_type_invalid');return['body'=>$body,'content_type'=>$type,'final_url'=>$current];
        }
        throw new RemoteAssetException('redirect_blocked');
    }

    private function resolvePublic(string$host):array{$ips=($this->resolver)($host);if(!$ips)throw new RemoteAssetException('dns_failed');foreach($ips as$ip)if(!Security::isPublicIp((string)$ip))throw new RemoteAssetException('ip_blocked');sort($ips);return$ips;}
    private function redirect(string$base,string$location):string{if(preg_match('~^https?://~i',$location))return SourceUrl::canonicalize($location);$parts=wp_parse_url($base);if(!is_array($parts))throw new \InvalidArgumentException();$origin=$parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.(int)$parts['port']:'');if(str_starts_with($location,'//'))return SourceUrl::canonicalize($parts['scheme'].':'.$location);if(str_starts_with($location,'/'))return SourceUrl::canonicalize($origin.$location);$directory=rtrim(str_replace('\\','/',dirname((string)($parts['path']??'/'))),'/');return SourceUrl::canonicalize($origin.($directory?'/'.ltrim($directory,'/'):'').'/'.$location);}
}
