@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Admins')

@section('vendor-style')
<!-- Vendor -->


<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
@endsection

@section('page-style')

@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/jquery.validate/jquery.validate.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/jquery.validate/additional-methods.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('page-script')
<script>
  init();

  var table = $('#table').DataTable({
    "stateSave": true,
    "ajax": {
      "url": "/accounts/admins/list",
      "type": "POST",
      "dataSrc": "",
      "headers": {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
    },
    "columns": [
    { "data": "admin_id" },
    { "data": "username" },
    { "data": "admin_name" },
    {
      "data": "is_active",
      "render": function(data, type, row) {
        return data
        ? '<span class="badge bg-success">Active</span>'
        : '<span class="badge bg-danger">Inactive</span>';
      }
    },
    {
        data: "account_date_created",
        title: "Date Created",
        render: function(data) {
            return new Date(data).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: 'numeric',
                minute: 'numeric',
                hour12: true
            });
        }
    },
    {
      "data": null,
      "orderable": false,
      "render": function(data, type, row) {
        return `
                    <button class="btn btn-sm btn-secondary" onclick="editEntry(${row.admin_id})">Edit</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteEntry(${row.admin_id})">Delete</button>
                `;
      }
    }
    ],
  });

  $('#addEntryForm').validate({
    onfocusout: false,
    rules:
    {
      username:
      {
        required: true,
      },
      password:
      {
        required: true,
      },
      admin_name:
      {
        required: true,
      },
    },
    submitHandler: function (form)
    {
      var formData = new FormData(form);
      $.ajax({
        type: "POST",
        url: "/accounts/admins/store",
        async: true,
        data: formData,
        cache: false,
        contentType: false,
        processData: false,
        beforeSend: function()
        {
          toastr.info('Please Wait!');
        },
        success: function(data)
        {
          if (data > 0)
          {
            toastr.success('Transaction Success!');
            hideBootstrapModal('#addEntryModal');
            table.ajax.reload(null, false);
          }
          else if (data == -1)
          {
            toastr.warning('Username is already existing!');
          }
          else
          {
            toastr.error('Please try again!');
          }
        },
        error: function(data)
        {
          toastr.error('Something went wrong!');
        }
      });
    },
    messages:
    {
    },
  });

  $('#editEntryForm').validate({
    onfocusout: false,
    rules:
    {
      _admin_name:
      {
        required: true,
      },
      _is_active:
      {
        required: true,
      },
    },
    submitHandler: function (form)
    {
      var formData = new FormData(form);
      $.ajax({
        type: "POST",
        url: "/accounts/admins/update",
        async: true,
        data: formData,
        cache: false,
        contentType: false,
        processData: false,
        beforeSend: function()
        {
          toastr.info('Please Wait!');
        },
        success: function(data)
        {
          if (data > 0)
          {
            toastr.success('Transaction Success!');
            hideBootstrapModal('#editEntryModal');
            table.ajax.reload(null, false);
          }
          else if (data == -1)
          {
            toastr.warning('Username is already existing!');
          }
          else
          {
            toastr.error('Please try again!');
          }
        },
        error: function(data)
        {
          toastr.error('Something went wrong!');
        }
      });
    },
    messages:
    {
    },
  });

  function editEntry(id)
  {
    $.ajax({
      type: "POST",
      dataType: 'json',
      url: "/accounts/admins/find/" + id,
      async: false,
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response)
      {
        toggleBootstrapModal('#editEntryModal');
        $('#uid').val(response['admin_id']);
        $('#_username').val(response['username']);
        $('#_admin_name').val(response['admin_name']);
        $('#_is_active').val(response['is_active']).trigger('change');
      },
    });
  }

  function deleteEntry(id) {
    $.ajax({
      type: "POST",
      dataType: 'json',
      url: "/accounts/admins/find/" + id,
      async: false,
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        Swal.fire({
          title: 'Are you sure to delete #' + id + '?',
          text: "You won't be able to revert this!",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, delete it!',
          cancelButtonText: 'Cancel',
          customClass: {
            confirmButton: 'btn btn-danger me-3',
            cancelButton: 'btn btn-label-secondary'
          },
          buttonsStyling: false
        }).then(function(result) {
          if (result.isConfirmed) {
            $.ajax({
              type: "DELETE",
              dataType: 'json',
              url: "/accounts/admins/" + id,
              headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
              },
              success: function(deleteResponse) {
                toastr.success('Transaction Success!');
                table.ajax.reload(null, false);
              },
              error: function(xhr) {
                toastr.error('Something went wrong!');
              }
            });
          }
        });
      },
      error: function(xhr) {
        toastr.error('Something went wrong!');
      }
    });
  }

  function init() {
    initializeSelect2ForAllModals();
  }

  function initializeSelect2ForAllModals() {
      $('.select2').each(function() {
        $(this).select2({ dropdownParent: $(this).parent()});
    })
  }

  $('#addEntryModal').on('hidden.bs.modal', function()
  {
    $("#addEntryForm").trigger("reset");
  });

  $('#editEntryModal').on('hidden.bs.modal', function()
  {
    $("#editEntryForm").trigger("reset");
  });


</script>
@endsection

@section('content')

<div class="container-fluid">
  <h4 class="py-3 breadcrumb-wrapper mb-4">
    <span class="text-muted fw-light">Menu /</span> <span class="text-muted fw-light">Accounts /</span> Admins
  </h4>


  <div class="row mb-3">
    <div class="col-md-12">
      <button class="btn btn-primary float-end" data-bs-toggle="modal" data-bs-target="#addEntryModal">Add New</button>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped" id="table" name="table">
          <thead>
            <tr>
              <th style="width: 5%">#</th>
              <th style="width: 20%">Username</th>
              <th>Name</th>
              <th>Is Active?</th>
              <th>Date Time Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>

          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<div class="modal fade" id="addEntryModal" tabindex="-1" aria-labelledby="addEntryModal" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="addEntryForm" name="addEntryForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Add New Entry</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username">
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password">
          </div>
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" id="admin_name" name="admin_name">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Add</button>
        </div>
      </form>
    </div>
  </div>
</div>


<div class="modal fade" id="editEntryModal" tabindex="-1" aria-labelledby="editEntryModal" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="editEntryForm" name="editEntryForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Edit Entry</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" id="_username" name="_username" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Password (Leave this empty if you don't need to update)</label>
            <input type="password" class="form-control" id="_password" name="_password">
          </div>
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" id="_admin_name" name="_admin_name">
          </div>
          <div class="mb-3">
            <label class="form-label">Is Active</label>
            <select class="form-select select2" id="_is_active" name="_is_active">
              <option disabled selected>Please select</option>
              <option value="1">Yes</option>
              <option value="0">No</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <input type="hidden" id="uid" name="uid">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-success">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection
