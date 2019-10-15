@extends('layouts.app')

@section('content')
<script>
$(document).ready(function() {
    $('#revisions').DataTable({
    //"order": [[ 2, "desc" ]],
    "columnDefs":[
        {"targets":[1,3], "className":'dt-right'},
        {"targets":[0], "bSortable":false}
    ]
    });
} );
</script>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
            <div class="card-header">{{ ($document_revisions[0]->document)->title }}</div>
                 <div class="card-body">
                    <table id="revisions" class="display" style="width:100%">
                        <thead>
                            <tr>
                            <th>Type</th>
                            <th>Created</th>
                            <th>Created By</th>
                            <th>Size</th>
                            </tr>
                        </thead>
                        <tbody>
                    @foreach($document_revisions as $dr)
                    <tr>
                        <td><img class="file-icon" src="/i/file-types/{{ ($dr->document)->icon($dr->path) }}.png" /></td>
                        <td data-order="{{ $dr->created_at }}">
                        <a href="/document-revision/{{$dr->id}}" target="_new">{{ date('F d, Y', strtotime($dr->created_at)) }}</a>
                        </td>
                        <td>{{ ($dr->user)->email }}</td>
                        <td data-order="{{$dr->size}}">{{ ($dr->document)->human_filesize($dr->size) }}</td>
                    </tr>
                    @endforeach
                        </tbody>
                    </table>
                 </div>
            </div>
        </div>
    </div>
</div>
@endsection