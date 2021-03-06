<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
	'created_at' => 'datetime',
	'updated_at' => 'datetime',
	'deleted_at' => 'datetime'
    ];

	protected $table = "users";

    public function roles(){
        return $this->hasMany('App\UserRole');
    }

    public function hasRole($role_name){
        $roles = $this->roles()->get();
        $role = \App\Role::where('name',$role_name)->first();
        $role_id = null;
        if($role){
            $role_id = $role->id;
        }
        foreach($roles as $r){
            if($role_id == $r->role_id)
            return true;
        }
        return false;
    }

    public function accessPermissions(){
        return \App\UserPermission::where('user_id','=',$this->id)->get();
    }

    public function hasPermission($collection_id, $permission_name){
        $user_permissions = $this->accessPermissions();
        /*
        check VIEW access first
        If the collection is Public or if the user has any permission on the collection
        */ 
        if($permission_name == 'VIEW'){
            $c = \App\Collection::find($collection_id);
            if($c->type == 'Public') return true;
            
            foreach($user_permissions as $u_p){
                if($u_p->collection_id == $collection_id) return true;
            }
        }
        foreach($user_permissions as $u_p){
            if($u_p->collection_id == $collection_id && 
                (($u_p->permission)->name == $permission_name || ($u_p->permission)->name == 'MAINTAINER')){
                return true;
            }
        }
        return false;
    }

    public function canEditDocument($document_id){
        $document = \App\Document::find($document_id);
        $collection_id = $document->collection_id;
        if($this->hasPermission($collection_id, 'MAINTAINER') || 
            $this->hasPermission($collection_id, 'EDIT_ANY') ||
            ($this->hasPermission($collection_id, 'EDIT_OWN') && $document->created_by == $this->id)){
            return true;
        }
        return false;
    }

    public function canDeleteDocument($document_id){
        $document = \App\Document::find($document_id);
        $collection_id = $document->collection_id;
        if($this->hasPermission($collection_id, 'MAINTAINER') || 
            $this->hasPermission($collection_id, 'DELETE_ANY') ||
            ($this->hasPermission($collection_id, 'DELETE_OWN') && $document->created_by == $this->id)){
            return true;
        }
        return false;
    }

    public function canApproveDocument($document_id){
        $document = \App\Document::find($document_id);
        $collection_id = $document->collection_id;
        if($this->hasPermission($collection_id, 'MAINTAINER') || 
            ($this->hasPermission($collection_id, 'APPROVE') && $document->created_by == $this->id)){
            return true;
        }
        return false;
    }
}
