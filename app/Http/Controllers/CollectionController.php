<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Collection;
use Illuminate\Support\Facades\Auth;
use Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Elasticsearch\ClientBuilder;
use App\StorageTypes;
use App\SpideredDomain;
use App\DesiredUrl;
use App\UrlSuppression;

class CollectionController extends Controller
{
    public function __construct()
    {
        //$this->middleware('collection_view');
    }

    public function index(){
        $collections = Collection::all();
        return view('collectionmanagement', ['collections'=>$collections, 'activePage'=>'Collections','titlePage'=>'Collections']);
    }

    public function add_edit_collection($collection_id){
        if($collection_id == 'new'){
            $collection = new \App\Collection();
        }
        else{
            $collection = \App\Collection::find($collection_id);
        }
	#$storage_types = StorageTypes::all();
	$storage_disks =  config('filesystems.disks');
        return view('collection-form', ['collection'=>$collection,'storage_disks'=>$storage_disks,'activePage'=>'Collection', 'titlePage'=>'Collection']);
    }

    public function list(){
        /*
         Get all public collections 
         plus collections to which the current user has access.
         Access to members-only collection is determined by db_table:user_permissions 
        */
        $user_collections = array();
        $user_permissions = empty(Auth::user()) ? array() : Auth::user()->accessPermissions();
        foreach($user_permissions as $u_p){
            if(!in_array($u_p->collection_id, $user_collections)){
                array_push($user_collections, $u_p->collection_id);
            }
        }
        $collections = Collection::whereIn('id', $user_collections)->orWhere('type','=','Public')->get();
        return view('collections', ['title'=>'Smart Repository','activePage'=>'collections','titlePage'=>'Collections','collections'=>$collections]);
    }

    public function save(Request $request){
         if(empty($request->input('collection_id'))){
            $c = new \App\Collection;
         }
         else{
            $c = \App\Collection::find($request->input('collection_id'));
         }
         $c->name = $request->input('collection_name');
         $c->description = $request->input('description');
         $c->type = empty($request->input('collection_type'))?'Public':$request->input('collection_type');
         $c->storage_drive = $request->input('storage_drive');
         $c->content_type = $request->input('content_type');
         $c->require_approval = $request->input('require_approval');
         $c->user_id = Auth::user()->id;
         try{
            $c->save();
            Session::flash('alert-success', 'Collection saved successfully!');
         }
         catch(\Exception $e){
            Session::flash('alert-danger', $e->getMessage());
            return redirect('/admin/collectionmanagement');
         }
         // maintainer ID
         if(!empty($request->input('maintainer'))){
            $maintainer = \App\User::where('email', '=', $request->input('maintainer'))->first();
            $permission = \App\Permission::where('name','=','MAINTAINER')->first();
            $maintainer_permission = \App\UserPermission::where('collection_id','=',$c->id)->where('permission_id','=',$permission->id)->first();
            $maintainer_id = empty($maintainer->id)? null : $maintainer->id;
            if($maintainer_permission){
                $maintainer_permission->delete();
            }
            if($maintainer_id){
                $new_maintainer_permission = new \App\UserPermission();
                $new_maintainer_permission->permission_id = $permission->id;
                $new_maintainer_permission->collection_id = $c->id;
                $new_maintainer_permission->user_id = $maintainer_id;
                $new_maintainer_permission->save();
            }
            else{
                Session::flash('alert-warning', 'Maintainer was not found');
            }
         } 
         // create a storage dir for this collection if it does not exist
         if (!file_exists(storage_path().'/app/smartarchive_assets/'.$c->id.'/0')) {
            mkdir(storage_path().'/app/smartarchive_assets/'.$c->id.'/0', 0777, true);
         }
         return redirect('/admin/collectionmanagement');
    }

    public function collection($collection_id){
        $collection = Collection::find($collection_id);
        $documents = \App\Document::where('collection_id','=',$collection_id)->orderby('updated_at','DESC')->paginate(100);
        return view('collection', ['collection'=>$collection, 'documents'=>$documents, 'activePage'=>'collection','titlePage'=>'Collections', 'title'=>'Smart Repository']);
    }

    public function collectionUsers($collection_id){
        $collection = Collection::find($collection_id);
        $user_permissions = \App\UserPermission::where('collection_id', '=', $collection_id)->get();
        //$user_permissions;
        $collection_users = array();
        foreach($user_permissions as $u_p){
            $collection_users[$u_p->user_id][] = $u_p;
        }
        return view('collection_users', ['collection'=>$collection, 'collection_users'=>$collection_users,'titlePage'=>'Collection Users','activePage'=>'Collection Users','title'=>'Collection Users']);
    }

    public function showCollectionUserForm($collection_id, $user_id=null){
        $user_permissions = array();
        $user = null;
	$has_approval=array();
	$has_approval = \App\Collection::where('id','=',$collection_id)->where('require_approval','=','1')->get();
        if(!empty($user_id)){
            $user = \App\User::find($user_id);
            $u_permissions = \App\UserPermission::where('user_id','=',$user_id)
                ->where('collection_id','=',$collection_id)->get();
            foreach($u_permissions as $u_p){
                $user_permissions['p'.$u_p->permission_id] = 1;
            }
        }
        return view('collection-user-form', ['collection'=>\App\Collection::find($collection_id), 
            'user'=>$user, 
            'user_permissions'=>$user_permissions,
	    'collection_has_approval'=>$has_approval,
            'title'=>'Collection User Form',
	    'activePage'=>'Collection User Form',
	    'titlePage'=> 'Collection User Form'				
	]);
    }

    public function saveUser(Request $request){
	/*
	// WHY IS THIS VALIDATION NEEDED ? 
	// Just check if the user exists in the database
	$request->validate([
    	'user_id' => 'email:rfc,dns'
	]);
	 */
        $user = \App\User::where('email','=',$request->user_id)->first();
        // first delete all permissions on the collection
	if($user){
	    \App\UserPermission::where('collection_id','=',$request->collection_id)
            ->where('user_id','=',$user->id)->delete(); 
          foreach($request->permission as $p){
            $user_permission = new \App\UserPermission;
            $user_permission->user_id = $user->id;
            $user_permission->collection_id = $request->collection_id;
            $user_permission->permission_id = $p; 
            $user_permission->save();
          }
	}
        return $this->collectionUsers($request->collection_id);
    }

    public function removeUser($collection_id, $user_id){
        \App\UserPermission::where('collection_id','=',$collection_id)
            ->where('user_id','=',$user_id)->delete(); 
        return $this->collectionUsers($collection_id);
    }

    public function getMetaFilteredDocuments($request, $documents){
        $all_meta_filters = Session::get('meta_filters');
        $meta_filters = empty($all_meta_filters[$request->collection_id])?null:$all_meta_filters[$request->collection_id];
        foreach($meta_filters as $mf){
            if($mf['operator'] == '='){
                $documents->whereHas('meta', function (Builder $query) use($mf){
                        $query->where('meta_field_id',$mf['field_id'])->where('value', $mf['value']);
                    }
                )->get();
            }
            else if($mf['operator'] == '>='){
                $documents->whereHas('meta', function (Builder $query) use($mf){
                        $query->where('meta_field_id',$mf['field_id'])->where('value', '>=', $mf['value']);
                    }
                )->get();
            }
            else if($mf['operator'] == '<='){
                $documents->whereHas('meta', function (Builder $query) use($mf){
                        $query->where('meta_field_id',$mf['field_id'])->where('value', '<=', $mf['value']);
                    }
                )->get();
            }
            else if($mf['operator'] == 'contains'){
                $documents->whereHas('meta', function (Builder $query) use($mf){
                        $query->where('meta_field_id',$mf['field_id'])->where('value', 'like', '%'.$mf['value'].'%');
                    }
                )->get();
            }
        }
        return $documents;
    }

    // wrapper function for search
    public function search(Request $request){
        if(!empty(env('SEARCH_MODE')) && env('SEARCH_MODE') == 'elastic'){
            return $this->searchElastic($request);
        }
        else{
            return $this->searchDB($request); 
        }
    }

    // elastic search
    public function searchElastic($request){
        $elastic_hosts = env('ELASTIC_SEARCH_HOSTS', 'localhost:9200');
        $hosts = explode(",",$elastic_hosts);
        $client = ClientBuilder::create()->setHosts($hosts)->build();
    
        $params = array();
        /*
        $params = [
            'index' => 'sr_documents',
            'body'  => [
                'query' => [
                    'bool'=>[
                        'filter' => [
                            'term'=> ['collection_id' => $request->collection_id]
                        ]
                    ]
                ]
            ]
        ];
        */
	$collection = \App\Collection::find($request->collection_id);
	if($collection->content_type == 'Uploaded documents'){
        $elastic_index = 'sr_documents';
        $documents = \App\Document::where('collection_id', $request->collection_id);
	}
	else{
        $elastic_index = 'sr_urls';
        $documents = \App\Url::where('collection_id', $request->collection_id);
	}
        $total_count = $documents->count();

        if(!empty($request->search['value']) && strlen($request->search['value'])>3){
            $search_term = $request->search['value'];
            $words = explode(' ',$search_term);
            /*
            $params['body']['query']['simple_query_string']['fields'] = ['text_content','title'];
            $params['body']['query']['simple_query_string']['query'] = $search_term;
            */
            foreach($words as $w){
                $params['body']['query']['bool']['must'][]['wildcard']['text_content']=$w.'*';
            }
            $params['body']['query']['bool']['filter']['term']['collection_id']=$request->collection_id;
        }
        $columns = array('type', 'title', 'size', 'updated_at');
        if(!empty($params)){
	    $params['index'] = $elastic_index;
	    $params['size'] = 100;// set a max size returned by ES
            $response = $client->search($params);
            $document_ids = array();
            foreach($response['hits']['hits'] as $h){
                $document_ids[] = $h['_id'];
            }
            $documents = $documents->whereIn('id', $document_ids);
        }
	$filtered_count = $documents->count();
        // get Meta filtered documents
        $all_meta_filters = Session::get('meta_filters');
        if(!empty($all_meta_filters[$request->collection_id])){
            $documents = $this->getMetaFilteredDocuments($request, $documents);
        }
        $documents = $documents->orderby($columns[$request->order[0]['column']],$request->order[0]['dir'])
             ->limit($request->length)->offset($request->start);

	    $has_approval = \App\Collection::where('id','=',$request->collection_id)->where('require_approval','=','1')->get();
        $results_data = $this->datatableFormatResults(
               array('request'=>$request, 'documents'=>$documents->get(), 'has_approval'=>$has_approval)
       	);
        $results= array(
            'data'=>$results_data,
            'draw'=>(int) $request->draw,
            'recordsTotal'=> $total_count,
            'recordsFiltered' => $filtered_count,
            'error'=> '',
        );
        return json_encode($results);
    }

    // db search (default)
    public function searchDB($request){
	$collection = \App\Collection::find($request->collection_id);
        $columns = array('type', 'title', 'size', 'updated_at');
	$has_approval = \App\Collection::where('id','=',$request->collection_id)->where('require_approval','=','1')->get();
	if($collection->content_type == 'Uploaded documents'){
        // if approval is involved, get list of documents based on permissions of the logged in user
            if(Auth::user()){ // and has permission APPROVE on this collection!!!
        	$documents_filtered = \App\Document::where('collection_id','=',$request->collection_id);
	    }
	    else{
		    if($has_approval->isEmpty()){
        	    $documents_filtered = \App\Document::where('collection_id','=',$request->collection_id);
		    }
		    else{
        	    $documents_filtered = \App\Document::where('collection_id','=',$request->collection_id)->whereNotNull('approved_on');
		    }
	    }
	}
	else if($collection->content_type == 'Web resources'){
        	$documents_filtered = \App\Url::where('collection_id','=',$request->collection_id);
	}
        // total number of viewable records
        $total_documents = $documents_filtered->count(); 

        // get Meta filtered documents
        $all_meta_filters = Session::get('meta_filters');
        if(!empty($all_meta_filters[$request->collection_id])){
            $documents_filtered = $this->getMetaFilteredDocuments($request, $documents_filtered);
        }

        // content search
        if(!empty($request->search['value']) && strlen($request->search['value'])>3){
            $documents_filtered = $documents_filtered->search($request->search['value']);
        }

            $filtered_count = $documents_filtered->count();
        if(!empty($request->embedded)){ 		
            $documents = $documents_filtered->limit($request->length)->offset($request->start)->get();
       	    $results_data = $this->datatableFormatResultsEmbedded(array('request'=>$request, 'documents'=>$documents, 'has_approval'=>$has_approval));
	}
	else{
            $documents = $documents_filtered->orderby($columns[$request->order[0]['column']],$request->order[0]['dir'])
            ->limit($request->length)->offset($request->start)->get();
            $results_data = $this->datatableFormatResults(array('request'=>$request, 'documents'=>$documents, 'has_approval'=>$has_approval));
	}
        
        $results= array(
            'data'=>$results_data,
            'draw'=>(int) $request->draw,
            'recordsTotal'=> $total_documents,
            'recordsFiltered' => $filtered_count,
            'error'=> '',
        );

        // log search query
        $search_log_data = array('collection_id'=> $request->collection_id, 
                'user_id'=> empty(\Auth::user()->id) ? null : \Auth::user()->id,
                'search_query'=> $request->search['value'], 
                'meta_query'=>'',
                'results'=>$filtered_count);
        if(!empty($request->search['value']) && strlen($request->search['value'])>3){
            $this->logSearchQuery($search_log_data);
        }

        return json_encode($results);
    }

    private function datatableFormatResults($data){
        $documents = $data['documents'];
        $request = $data['request'];
        $has_approval = $data['has_approval'];

	$collection = \App\Collection::find($request->collection_id);

        $results_data = array();
        foreach($documents as $d){
            $action_icons = '';

	    if($collection->content_type == 'Uploaded documents'){
            	$revisions = $d->revisions;
            	$r_count = count($revisions);
            	if($r_count > 1){
               		$filter_count = ($r_count > 9) ? '' : '_'.$r_count;
                	$action_icons .= '<a class="btn btn-primary btn-link" href="/document/'.$d->id.'/revisions" title="'.$r_count.' revisions"><i class="material-icons">filter'.$filter_count.'</i></a>';
            	}
		$action_icons .= '<a class="btn btn-primary btn-link" title="Download" href="/collection/'.$request->collection_id.'/document/'.$d->id.'" target="_blank"><i class="material-icons">cloud_download</i></a>';
	    }
  	    else if ($collection->content_type == 'Web resources'){		
		$action_icons .= '<a class="btn btn-primary btn-link" href="'.$d->url.'" target="_blank"><i class="material-icons">link</i></a>';
	    }

	    $action_icons .= '<a class="btn btn-primary btn-link" title="Information and more" href="/collection/'.$request->collection_id.'/document/'.$d->id.'/details"><i class="material-icons">info</i></a>';
	    if($collection->content_type == 'Uploaded documents'){
            if(Auth::user()){
                if(Auth::user()->canApproveDocument($d->id) && !$has_approval->isEmpty()){
			if(!empty($d->approved_on)){
                $action_icons .= '<a class="btn btn-primary btn-link" href="/document/'.$d->id.'/edit" title="UnApprove document"><i class="material-icons">done</i></a>';
			}
			else{
                $action_icons .= '<a class="btn btn-primary btn-link" href="/document/'.$d->id.'/edit" title="Approve document"><i class="material-icons">close</i></a>';
			}
		}
                if(Auth::user()->canEditDocument($d->id)){
                $action_icons .= '<a class="btn btn-success btn-link" href="/document/'.$d->id.'/edit" title="Create a new revision"><i class="material-icons">edit</i></a>';
                }
                if(Auth::user()->canDeleteDocument($d->id)){
                $action_icons .= '<span class="btn btn-danger btn-link confirmdelete" onclick="showDeleteDialog('.$d->id.');" title="Delete document"><i class="material-icons">delete</i></span>';
                }
            }
	    } // if collection's content-type == Uploaded documents
	    $title = $d->title.': '. substr($d->text_content, 0, 100).' ...';
            $results_data[] = array(
                'type' => array('display'=>'<a href="/collection/'.$request->collection_id.'/document/'.$d->id.'/details"><img class="file-icon" src="/i/file-types/'.$d->icon().'.png" /></a>', 'filetype'=>$d->icon()),
                'title' => $title,
                'size' => array('display'=>$d->human_filesize(), 'bytes'=>$d->size),
                'updated_at' => array('display'=>date('d-m-Y', strtotime($d->updated_at)), 'updated_date'=>$d->updated_at),
                'actions' => $action_icons);
        }
        return $results_data;
    }

    public function addMetaFilter(Request $request){
        // set filters in session and return to the collection view 
        $meta_filters = Session::get('meta_filters');
        if(!empty($request->meta_value)){
            $meta_filters[$request->collection_id][] = array(
                'filter_id'=>\Uuid::generate()->string,
                'field_id'=>$request->meta_field,
                'operator'=>$request->operator,
                'value'=>$request->meta_value
            );
        }
        Session::put('meta_filters', $meta_filters);
        return redirect('/collection/'.$request->collection_id.'/metafilters');
    }
    
    public function metaInformation($collection_id, $meta_field_id=null){
        $collection = \App\Collection::find($collection_id);
        if(empty($meta_field_id)){
            $edit_field = new \App\MetaField;
        }
        else{
            $edit_field = \App\MetaField::find($meta_field_id);
        }
        $meta_fields = $collection->meta_fields()->orderby('display_order','ASC')->get();
        return view('metainformation', ['collection'=>$collection, 
                'edit_field'=>$edit_field, 
                'meta_fields'=>$meta_fields,
		'activePage' =>'Collections Meta Data',
		'titlePage'=>'Collections Metadata Fields']);
    }

    public function saveMeta(Request $request){
        $collection = \App\Collection::find($request->input('collection_id'));
        if(empty($request->input('meta_field_id'))){
            $meta_field = new \App\MetaField;
        }
        else{
            $meta_field = \App\MetaField::find($request->input('meta_field_id'));
        }
        $meta_field->collection_id = $request->input('collection_id');
        $meta_field->label = $request->input('label');
        $meta_field->placeholder = $request->input('placeholder');
        $meta_field->type = $request->input('type');
        $meta_field->options = $request->input('options');
        $meta_field->display_order = $request->input('display_order');
        $meta_field->save();
        return $this->metaInformation($request->input('collection_id'));
    }

    public function deleteMetaField($collection_id,$meta_field_id){
        $meta_field = \App\MetaField::find($meta_field_id);
        $collection_id = $meta_field->collection_id;
        $meta_field->delete();
        return redirect('/collection/'.$collection_id.'/meta');
    }

    public function metaFiltersForm($collection_id){
        $collection = \App\Collection::find($collection_id);
        return view('metasearch', ['collection'=>$collection, 
            'activePage'=>'Set Meta Filters',
            //'titlePage'=>'Set Meta Filters',
            'title'=>'Smart Repository'
            ]
            );
    }
    
    public function removeMetaFilter($collection_id, $filter_id){
        $all_meta_filters = Session::get('meta_filters');
        $new_collection_filters = array();
        foreach($all_meta_filters[$collection_id] as $mf){
            if($mf['filter_id'] == $filter_id) continue;
            $new_collection_filters[] = $mf;
        }
        $all_meta_filters[$collection_id] = $new_collection_filters;
        Session::put('meta_filters', $all_meta_filters);
        return redirect('/collection/'.$collection_id);
    }

    public function removeAllMetaFilters($collection_id){
        $all_meta_filters = Session::get('meta_filters');
        $all_meta_filters[$collection_id] = null;
        Session::put('meta_filters', $all_meta_filters);
        return redirect('/collection/'.$collection_id);
    }

    public function logSearchQuery($data){
        $search_log_entry = new \App\Searches;
        $search_log_entry->collection_id = $data['collection_id']; 
        $search_log_entry->meta_query = $data['meta_query']; 
        $search_log_entry->search_query = $data['search_query']; 
        $search_log_entry->user_id = $data['user_id']; 
        $search_log_entry->results = $data['results']; 
        $search_log_entry->save();
    }

    public function deleteCollection(Request $request){
        $collection = \App\Collection::find($request->collection_id);

    	if ($collection != null) {
	if(!empty($request->delete_captcha) &&
                $request->delete_captcha == $request->delete_captcha){
       	 	if($collection->delete()){
            	Session::flash('alert-success', 'Collection deleted successfully!');
       	 	return redirect('/admin/collectionmanagement');
		}
        }
        else{
                Session::flash('alert-danger', 'Please fill Captcha');
                return redirect('/admin/collectionmanagement');
        }
    	}

    }

    public function collection_list(){
        /*
         Get all public collections 
         plus collections to which the current user has access.
         Access to members-only collection is determined by db_table:user_permissions 
        */
        $user_collections = array();
        $user_permissions = empty(Auth::user()) ? array() : Auth::user()->accessPermissions();
        foreach($user_permissions as $u_p){
            if(!in_array($u_p->collection_id, $user_collections)){
                array_push($user_collections, $u_p->collection_id);
            }
        }
        $collections = Collection::whereIn('id', $user_collections)->orWhere('type','=','Public')->get();
	return $collections;
    }


    private function datatableFormatResultsEmbedded($data){
        $documents = $data['documents'];
        $request = $data['request'];

	$collection = \App\Collection::find($request->collection_id);

        $results_data = array();

        foreach($documents as $d){
	    $title = $d->title.'<br />'. substr($d->text_content, 0, 100).' ...';
            $results_data[] = array(
                'type' => array('display'=>'<a href="/collection/'.$request->collection_id.'/document/'.$d->id.'/details"><img class="file-icon" src="'.env('APP_URL').'/i/file-types/'.$d->icon().'.png" style="width:20px;"/></a>', 'filetype'=>$d->icon()),
                'title' => '<a href="'.env('APP_URL').'/collection/'.$request->collection_id.'/document/'.$d->id.'/details" target="_blank">'.$title.'</a>',
                'size' => array('display'=>$d->human_filesize(), 'bytes'=>$d->size),
                'updated_at' => array('display'=>date('d-m-Y', strtotime($d->updated_at)), 'updated_date'=>$d->updated_at),
                );
        }
        return $results_data;
    }

    public function collectionUrls($collection_id){
        if($collection_id == 'new'){
            $collection = new \App\Collection();
        }
        else{
            $collection = \App\Collection::find($collection_id);
        }
        return view('save_exclude_sites', ['collection'=>$collection,'activePage'=>'Collection', 'titlePage'=>'Collection']);
    }

    public function saveCollectionUrls(Request $request){
	$domain_link = $request->spidered_domain;
	$existing_domains = array();
	$sd = SpideredDomain::all();
	foreach($sd as $domain){
		$existing_domains[] = $domain->web_address;
	}		
	if(!in_array($domain_link,$existing_domains)){
		$sd = new \App\SpideredDomain;
		$sd->collection_id = $request->input('collection_id');
		$sd->web_address = $domain_link;
         	try{
	    	$sd->save();
		$last_insert_id = $sd->id;
		if(!empty($request->input('save_urls'))){
		$this->saveDesiredUrls($last_insert_id,$request);		
		}
		elseif(!empty($request->input('exclude_urls'))){
		$this->excludeUrls($last_insert_id,$request);		
		}
            	Session::flash('alert-success', 'Site URLs saved successfully!');
            	return redirect('/collection/'.$request->collection_id.'/save_exclude_sites');
         	}
         	catch(\Exception $e){
            	Session::flash('alert-danger', $e->getMessage());
            	return redirect('/collection/'.$request->collection_id.'/save_exclude_sites');
         	}
	} ##if ends for existing domains check
	else{
            	Session::flash('alert-danger', 'Domain already spidered.');
            	return redirect('/collection/'.$request->collection_id.'/save_exclude_sites');
	}
/*
use App\SpideredDomain;
use App\DesiredUrl;
use App\UrlSuppression;
*/
    }

    public function saveDesiredUrls($spidered_domain_id, $request){
	$url_start_patterns = explode("\n",$request->input('save_urls'));
	$collection_id = $request->input('collection_id');
#DB::enableQueryLog();
	foreach($url_start_patterns as $url){
		$su = new \App\DesiredUrl;	
		$su->collection_id = $collection_id;
		$su->url_start_pattern = rtrim(ltrim($url));
		$su->spidered_domain_id = $spidered_domain_id;
	    	$su->save();
	}
#dd(DB::getQueryLog());
    }	

    public function excludeUrls($spidered_domain_id, $request){
	$url_start_patterns = explode("\n",$request->input('save_urls'));
	$collection_id = $request->input('collection_id');
#DB::enableQueryLog();
	foreach($url_start_patterns as $url){
		$su = new \App\UrlSuppression;
		$su->collection_id = $collection_id;
		$su->url_start_pattern = rtrim(ltrim($url));
		$su->spidered_domain_id = $spidered_domain_id;
	    	$su->save();
	}
#dd(DB::getQueryLog());
    }	
##########################################
## Class Ends
}
