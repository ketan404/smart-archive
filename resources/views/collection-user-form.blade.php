@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ $collection->name }} :: User Permissions</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                   <form method="post" action="/collection/{{ $collection->id }}/savecollectionuser">
                    @csrf()
                    <input type="hidden" name="collection_id" value="{{$collection->id}}" />
                   <div class="form-group row">
                   <label for="user_id" class="col-md-4 col-form-label text-md-right">User ID</label> 
                    <div class="col-md-6">
                    <input type="text" name="user_id" id="user_id" class="form-control" placeholder="User ID" 
                    value="@if(!empty($user->id)){{ $user->email }}@endif" />
                    </div>
                   </div>
                    @foreach(\App\Permission::all() as $p)
                   <div class="form-group row">
                   <label for="permission" class="col-md-4 col-form-label text-md-right"></label> 
                    <div class="col-md-6">
                    <input type="checkbox"  name="permission[]" value="{{ $p->id }}" 
                    @if(!empty($user_permissions['p'.$p->id]))
                     checked 
                    @endif
                    />  {{ $p->name }}
                    </div>
                   </div>
                    @endforeach
                   <div class="form-group row mb-0"><div class="col-md-8 offset-md-4"><button type="submit" class="btn btn-primary">
                                    Save
                                </button> 
                     </div></div> 
                   </form> 
                </div>
            </div>

        </div>
    </div>
</div>
@endsection