<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Project\Eloquent\WebhookEvents;
use Exception;

class TriggerWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trigger:webhooks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $timestamp = time();

        $hasRun = WebhookEvents::where('flag', '<>', 0)->exists();
        if ($hasRun)
        {
            return;
        }

        while(true)
        {
            WebhookEvents::where('flag', 0)->update([ 'flag' => $timestamp ]);

            $events = WebhookEvents::where('flag', $timestamp)->orderBy('_id', 'asc')->get();
            if ($events->isEmpty())
            {
                break;
            }

            foreach ($events as $event)
            {
                $header = [ 'Content-Type: application/json', 'Expect:', 'X-Actionview-Token: ' . ($event->token ?: '') ];
                $this->curlPost($event->request_url, $header, $event->data ?: []);
                $event->delete();
            }
        }
    }

    /**
     * The curl request the hook.
     *
     * @var array nodes
     */
    public static function curlPost($url, $header=[], $data=[], $await=5)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $await);
        curl_setopt($ch, CURLOPT_TIMEOUT, $await);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * The curl request the hook.
     *
     * @var array nodes
     */
    public static function multiCurlPost(array $nodes, $await=5)
    {
        $mh = curl_multi_init();

        $chs = [];
        foreach($nodes as $key => $node)
        {
            $chs[$key] = curl_init();

            curl_setopt($chs[$key], CURLOPT_URL, isset($node['url']) ? $node['url'] : '');
            curl_setopt($chs[$key], CURLOPT_HEADER, 0);
            curl_setopt($chs[$key], CURLOPT_HTTPHEADER, [ 'Content-Type: application/json', 'Expect:' ]);
            curl_setopt($chs[$key], CURLOPT_CONNECTTIMEOUT, $await);
            curl_setopt($chs[$key], CURLOPT_TIMEOUT, $await);
            curl_setopt($chs[$key], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chs[$key], CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($chs[$key], CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($chs[$key], CURLOPT_POST, true);
            curl_setopt($chs[$key], CURLOPT_POSTFIELDS, isset($node['data']) && is_array($node['data']) ? json_encode($node['data']) : []);

            curl_multi_add_handle($mh, $chs[$key]);
        }

        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK)
        {
            if (curl_multi_select($mh) == -1)
            {
                usleep(100);
            }

            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }

        $results = [];
        foreach($nodes as $key => $node)
        {
            $tmp = [];
            $ecode = curl_errno($chs[$key]);
            if ($ecode > 0)
            {
                $tmp = [ 'ecode' => $ecode, 'contents' => curl_error($chs[$key]) ];
            }
            else
            {
                $tmp = [ 'ecode' => $ecode, 'contents' => curl_multi_getcontent($chs[$key]) ];
            }
            $results[] = $tmp;

            curl_multi_remove_handle($mh, $chs[$key]);
            curl_close($chs[$key]);
        }

        curl_multi_close($mh);
    }
}
