<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Document;
use App\Collection;
use Elasticsearch\ClientBuilder;

class RebuildElasticIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ES:RebuildElasticIndex {collection_id : ID of the collection}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuilds Elastic Index';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $collection_id = $this->argument('collection_id');
        $c = Collection::find($collection_id);
        echo "Rebuilding elastic index of ".$c->name."\n";
	if($c->content_type == 'Uploaded documents'){
		$index = 'sr_documents';
        	$docs = $c->documents;
	}
	else if($c->content_type == 'Web resources'){
		$index = 'sr_urls';
        	$docs = $c->urls;
	}
        
        $elastic_hosts = env('ELASTIC_SEARCH_HOSTS', 'localhost:9200');
        $hosts = explode(",",$elastic_hosts);
        $client = ClientBuilder::create()->setHosts($hosts)->build();
	// first, clear the old index
	$client->indices()->delete(array('index'=>$index));

        foreach($docs as $d){
            $body = $d->toArray();
            $body['collection_id'] = $c->id;
            $params = [
                'index' => $index,
                'id'    => $d->id,
                'body'  => $body
            ];

            $response = $client->index($params);
            print_r($response);
        }
    }
}
