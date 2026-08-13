<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class SourceConnectionTester
{
    private $transport;
    private $resolver;

    public function __construct(?callable $transport = null, ?callable $resolver = null)
    {
        $this->transport = $transport ?? static fn(string $url, array $args): mixed => wp_safe_remote_get($url, $args);
        $this->resolver = $resolver ?? static function (string $host): array {
            if (filter_var($host, FILTER_VALIDATE_IP)) return [$host];
            $ips = [];
            $records = function_exists('dns_get_record') ? @dns_get_record($host, DNS_A | DNS_AAAA) : false;
            foreach (is_array($records) ? $records : [] as $record) {
                if (!empty($record['ip'])) $ips[] = $record['ip'];
                if (!empty($record['ipv6'])) $ips[] = $record['ipv6'];
            }
            if (!$ips) foreach (@gethostbynamel($host) ?: [] as $ip) $ips[] = $ip;
            return array_values(array_unique($ips));
        };
    }

    public function test(string $url, array $allowedDomains): array
    {
        $started = microtime(true);
        $testId = bin2hex(random_bytes(8));
        $diagnostics = ['test_id'=>$testId,'redirect_hosts'=>[],'_started'=>$started];
        try { $current = SourceUrl::canonicalize($url); }
        catch (\Throwable $e) { throw new SourceTestException('url_invalid',$testId,$diagnostics,$e); }
        $allowed = array_values(array_filter(array_map([SourceUrl::class,'normalizeHost'],$allowedDomains)));

        for ($hop=0;$hop<=3;$hop++) {
            $parts=wp_parse_url($current);$host=SourceUrl::normalizeHost((string)($parts['host']??''));
            if($host===''||!Security::validateFeedUrl($current,$allowed))throw new SourceTestException('host_invalid',$testId,$diagnostics);
            $before=$this->resolvePublic($host,$testId,$diagnostics);
            $response=($this->transport)($current,[
                'timeout'=>20,'redirection'=>0,'reject_unsafe_urls'=>true,'limit_response_size'=>2*1024*1024,
                'user-agent'=>'WordPress-News-Bot/'.(defined('WPNB_VERSION')?WPNB_VERSION:'dev'),
                'headers'=>['Accept'=>'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.1'],
            ]);
            if(is_wp_error($response))throw new SourceTestException('http_failed',$testId,$diagnostics);
            $after=$this->resolvePublic($host,$testId,$diagnostics);
            if(!array_intersect($before,$after))throw new SourceTestException('dns_failed',$testId,$diagnostics);
            $status=(int)wp_remote_retrieve_response_code($response);$diagnostics['http_status']=$status;
            if(in_array($status,[301,302,303,307,308],true)){
                $location=trim((string)wp_remote_retrieve_header($response,'location'));
                if($location===''||$hop===3)throw new SourceTestException('redirect_blocked',$testId,$diagnostics);
                try{$current=$this->resolveRedirect($current,$location);}catch(\Throwable$e){throw new SourceTestException('redirect_blocked',$testId,$diagnostics,$e);}
                $redirectHost=SourceUrl::normalizeHost((string)(wp_parse_url($current)['host']??''));
                if($redirectHost!=='')$diagnostics['redirect_hosts'][]=$redirectHost;
                if($redirectHost===''||!Security::validateFeedUrl($current,$allowed))throw new SourceTestException('redirect_blocked',$testId,$diagnostics);
                try{$this->resolvePublic($redirectHost,$testId,$diagnostics);}catch(SourceTestException$e){throw new SourceTestException('redirect_blocked',$testId,$diagnostics,$e);}
                continue;
            }
            if($status<200||$status>=300)throw new SourceTestException('http_status_invalid',$testId,$diagnostics);
            $contentType=strtolower(trim(explode(';',(string)wp_remote_retrieve_header($response,'content-type'))[0]));$diagnostics['content_type']=$contentType;
            $body=(string)wp_remote_retrieve_body($response);$diagnostics['response_bytes']=strlen($body);
            if($body==='')throw new SourceTestException('body_empty',$testId,$diagnostics);
            libxml_use_internal_errors(true);$xml=simplexml_load_string($body,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOCDATA);$errors=libxml_get_errors();libxml_clear_errors();
            if($xml===false){$diagnostics['parser_error_class']='libxml:'.(int)($errors[0]->code??0);$code=str_contains($contentType,'html')?'content_type_invalid':'xml_invalid';throw new SourceTestException($code,$testId,$diagnostics);}
            $root=strtolower($xml->getName());$feedType=match($root){'rss'=>'RSS 2.0','rdf','rdf:rdf'=>'RSS 1.0/RDF','feed'=>'Atom',default=>''};
            if($feedType==='')throw new SourceTestException(str_contains($contentType,'html')?'content_type_invalid':'feed_invalid',$testId,$diagnostics);
            try{$items=(new FeedParser())->parse($body);}catch(\Throwable$e){$diagnostics['parser_error_class']=get_class($e);throw new SourceTestException('xml_invalid',$testId,$diagnostics,$e);}
            $latest='';foreach($items as$item){$date=(string)($item['published_at']??'');if($date!==''&&($latest===''||strtotime($date)>strtotime($latest)))$latest=$date;}
            $diagnostics+=['feed_type'=>$feedType,'duration_ms'=>max(0,(int)round((microtime(true)-$started)*1000)),'final_host'=>$host];
            return ['result_code'=>'success','test_id'=>$testId,'http_status'=>$status,'content_type'=>$contentType,'response_bytes'=>strlen($body),'feed_type'=>$feedType,'item_count'=>count($items),'last_item_date'=>$latest,'duration_ms'=>$diagnostics['duration_ms'],'final_host'=>$host,'redirect_hosts'=>$diagnostics['redirect_hosts']];
        }
        throw new SourceTestException('redirect_blocked',$testId,$diagnostics);
    }

    private function resolvePublic(string $host,string $testId,array $diagnostics):array
    {
        $ips=($this->resolver)($host);if(!$ips)throw new SourceTestException('dns_failed',$testId,$diagnostics);
        foreach($ips as$ip)if(!Security::isPublicIp((string)$ip))throw new SourceTestException('ip_blocked',$testId,$diagnostics+['dns_result_count'=>count($ips)]);
        sort($ips);return$ips;
    }
    private function resolveRedirect(string$base,string$location):string
    {
        if(preg_match('~^https?://~i',$location))return SourceUrl::canonicalize($location);
        $parts=wp_parse_url($base);if(!is_array($parts))throw new \InvalidArgumentException();$port=isset($parts['port'])?':'.(int)$parts['port']:'';$origin=$parts['scheme'].'://'.$parts['host'].$port;
        if(str_starts_with($location,'//'))return SourceUrl::canonicalize($parts['scheme'].':'.$location);
        if(str_starts_with($location,'/'))return SourceUrl::canonicalize($origin.$location);
        $path=(string)($parts['path']??'/');$directory=rtrim(str_replace('\\','/',dirname($path)),'/');return SourceUrl::canonicalize($origin.($directory?'/'.ltrim($directory,'/'):'').'/'.$location);
    }
}
