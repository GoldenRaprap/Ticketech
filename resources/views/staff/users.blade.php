@extends('layouts.staff')
@section('title', 'User Management')
@section('content')
<div class="staff-heading"><div><h1>User Management</h1><p>Manage staff access shown in the prototype.</p></div><button class="btn btn-primary" type="button">+ Add User</button></div>
<section class="panel"><div class="toolbar"><input placeholder="Search users" aria-label="Search users"><select aria-label="Filter by role"><option>All roles</option><option>Admin</option><option>Employee / IT Staff</option></select><select aria-label="Filter by status"><option>All statuses</option><option>Active</option><option>Inactive</option></select></div>
<div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@foreach ([['Jordan Davis','jordan.davis@school.edu','Admin','Active'],['IT Staff A','staff.a@school.edu','Employee / IT Staff','Active'],['IT Staff B','staff.b@school.edu','Employee / IT Staff','Active'],['IT Staff C','staff.c@school.edu','Employee / IT Staff','Inactive']] as $user)
<tr><td>{{ $user[0] }}</td><td>{{ $user[1] }}</td><td>{{ $user[2] }}</td><td><span class="badge">{{ $user[3] }}</span></td><td><button class="btn btn-secondary btn-small" type="button">Edit</button></td></tr>
@endforeach
</tbody></table></div></section>
@endsection