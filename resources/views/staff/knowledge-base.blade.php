@extends('layouts.staff')
@section('title', 'Knowledge Base')
@section('content')
<div class="staff-heading"><div><h1>Knowledge Base</h1><p>Manage help articles · sample content only.</p></div><button class="btn btn-primary" type="button">+ New Article</button></div>
<section class="panel"><div class="toolbar"><input placeholder="Search articles" aria-label="Search articles"><select aria-label="Filter article category"><option>All categories</option><option>Bluebook</option><option>Network</option><option>Hardware</option></select></div><div class="table-wrap"><table><thead><tr><th>Article</th><th>Category</th><th>Visibility</th><th>Last Updated</th><th>Actions</th></tr></thead><tbody>
@foreach ([['Getting started with Bluebook','Bluebook','Public','Sep 20, 2026'],['Connect to campus Wi-Fi','Network','Public','Sep 18, 2026'],['Reset your school account password','Accounts','Public','Sep 12, 2026'],['Request equipment support','Hardware','Public','Sep 08, 2026']] as $article)<tr><td>{{ $article[0] }}</td><td>{{ $article[1] }}</td><td><span class="badge">{{ $article[2] }}</span></td><td>{{ $article[3] }}</td><td><button class="btn btn-secondary btn-small" type="button">Edit</button></td></tr>@endforeach
</tbody></table></div></section>
@endsection