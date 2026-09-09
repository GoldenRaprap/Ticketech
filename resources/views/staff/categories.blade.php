@extends('layouts.staff')
@section('title', 'Categories')
@section('content')
<div class="staff-heading"><div><h1>Categories</h1><p>Support areas available on the public help desk.</p></div><button class="btn btn-primary" type="button">+ Add Category</button></div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Category</th><th>Description</th><th>Tickets</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@foreach ([['Registrar','Student records and enrollment support',8],['Bluebook','Online learning platform',14],['PRIISM','Student information portal',6],['Network','Campus network and internet',11],['Hardware','Computers and equipment',9],['Software','Applications and licensed tools',7],['Other','Other IT concerns',4]] as [$category, $description, $tickets])
<tr><td><strong>{{ $category }}</strong></td><td>{{ $description }}</td><td>{{ $tickets }}</td><td><span class="badge">Active</span></td><td><button class="btn btn-secondary btn-small" type="button">Edit</button></td></tr>
@endforeach
</tbody></table></div></section>
@endsection