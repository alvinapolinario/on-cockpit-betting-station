@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Salaries')

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
<style>
  .hidden{
    display: none !important;
  };
</style>
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
      "url": "/salaries/list",
      "type": "POST",
      "data" : function(d)
      {
        d.event_id = $('#event_id').val();
      },
      "dataSrc": "",
      "headers": {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
    },
    "columns": [
      { "data": "salary_id" },
      { "data": "employee_name" },
      { "data": "employee_type" },
      {
        "data": null,
        "render": function(data, type, row) {
          return formatCurrency(data.daily_rate);
        }
      },
      {
        "data": null,
        "render": function(data, type, row) {
          return formatCurrency(data.overtime_rate);
        }
      },
      { "data": "total_overtime" },
      {
        "data": null,
        "render": function(data, type, row) {
          return formatCurrency(data.bonus);
        }
      },
      {
        "data": null,
        "render": function(data, type, row) {
          return formatCurrency(data.daily_rate + (data.overtime_rate * data.total_overtime) + data.bonus);
        }
      },
      {
        "data": null,
        "orderable": false,
        "render": function(data, type, row) {
          var buttons = '';
          buttons +=  `
                      <button class="btn btn-sm btn-secondary" onclick="editEntry(${row.salary_id})">Edit</button>
                      <button class="btn btn-sm btn-danger" onclick="deleteEntry(${row.salary_id})">Delete</button>
                  `;

                  return buttons;
        }
      }
    ],
    "drawCallback": function (settings) {
      let api = this.api();
      let total = 0;

      api.rows({ page: 'current' }).data().each(function (data) {
        total += parseFloat(data.daily_rate) + (parseFloat(data.overtime_rate) * parseFloat(data.total_overtime)) + parseFloat(data.bonus);
      });

      $('#totalPayout').text(total.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }));
    },

  });

  $('#addEntryForm').validate({
    onfocusout: false,
    rules:
    {
      employee_name:
      {
        required: true,
      },
      employee_type:
      {
        required: true,
      },
      daily_rate:
      {
        required: true,
      },
      overtime_rate:
      {
        required: true,
      },
      total_overtime:
      {
        required: true,
      },
      bonus:
      {
        required: true,
      },
    },
    submitHandler: function (form)
    {
      var formData = new FormData(form);
      $.ajax({
        type: "POST",
        url: "/salaries/store/" + $('#event_id').val(),
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
      _employee_name:
      {
        required: true,
      },
      _employee_type:
      {
        required: true,
      },
      _daily_rate:
      {
        required: true,
      },
      _overtime_rate:
      {
        required: true,
      },
      _total_overtime:
      {
        required: true,
      },
      _bonus:
      {
        required: true,
      },
    },
    submitHandler: function (form)
    {
      var formData = new FormData(form);
      $.ajax({
        type: "POST",
        url: "/salaries/update",
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
      url: "/salaries/find/" + id,
      async: false,
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response)
      {
        toggleBootstrapModal('#editEntryModal');
        $('#uid').val(response['salary_id']);
        $('#_employee_type').val(response['employee_type']).trigger('change');
        $('#_employee_name').val(response['employee_name']);
        $('#_daily_rate').val(response['daily_rate']);
        $('#_overtime_rate').val(response['overtime_rate']);
        $('#_total_overtime').val(response['total_overtime']);
        $('#_bonus').val(response['bonus']);
      },
    });
  }

  function deleteEntry(id) {
    $.ajax({
      type: "POST",
      dataType: 'json',
      url: "/salaries/find/" + id,
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
              url: "/salaries/" + id,
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
    loadEvents();
  }


  function loadEvents() {
    $("#event_id").prop("disabled", true);
    $.ajax({
      type: "POST",
      dataType: 'json',
      url: "/events/list",
      headers: {
        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
      },
      success: function (response) {
        let dropdown = $('#event_id');
        dropdown.empty().append('<option disabled selected>Please Select</option>');

        $.each(response, function (index, event) {
          dropdown.append(`<option value="${event.event_id}" data-status="${event.event_status}">${event.event_date} - ${event.event_status} - ${event.event_name}</option>`);
        });

        $("#event_id").prop("disabled", false);
      },
      error: function() {
        toastr.error("Failed to load events.");
      }
    });
  }

  $("#event_id").change(function() {
    $("#main").removeClass("hidden");
    table.ajax.reload();
  });

  $('#employee_type').on('change', function () {
  const selected = $(this).val();
  if (selected === 'Teller') {
    $('#includeAllTellersContainer').removeClass('hidden');
  } else {
    $('#includeAllTellersContainer').addClass('hidden');
    $('#include_all_tellers').prop('checked', false);
  }
  $('#div_employee_name').removeClass("hidden"); // Always show it after changing type
});

$('#include_all_tellers').on('change', function () {
  if ($(this).is(':checked')) {
    $('#div_employee_name').addClass("hidden");
  } else {
    $('#div_employee_name').removeClass("hidden");
  }
});



  function initializeSelect2ForAllModals() {
      $('.select2').each(function() {
        $(this).select2({ dropdownParent: $(this).parent()});
    })
  }

  function formatCurrency(amount) {
    let formatted = new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount);

    return formatted.replace(/₱\s?/, ''); // Removes ₱ symbol
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
    <span class="text-muted fw-light">Menu /</span> <span class="text-muted fw-light">Accounts /</span> Salaries
  </h4>


  <div class="row">
    <div class="col-md-6">
      <div class="mb-3">
        <label class="form-label">Event</label>
        <select class="form-select select2" id="event_id" name="event_id"></select>
      </div>
    </div>
  </div>

  <hr>

  <div class="hidden" id="main">


    <div class="row mb-3 mt-5">
      <div class="col-md-12">
        <h3 class="mb-3 fw-lighter float-start">Total Salaries: <span class="text-primary" id="totalPayout"> 0</span></h3>

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
                <th>Name</th>
                <th>Type</th>
                <th>Daily Rate</th>
                <th>Overtime Rate</th>
                <th>Total Overtime</th>
                <th>Bonus</th>
                <th>Total Pay</th>
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
            <label class="form-label">Type</label>
            <select class="form-select select2" id="employee_type" name="employee_type">
              <option disabled selected>Please select</option>
              <option value="Supervisor">Supervisor</option>
              <option value="Assistant Supervisor">Assistant Supervisor</option>
              <option value="I.T.">I.T.</option>
              <option value="Teller">Teller</option>
              <option value="Teller Trainee">Teller Trainee</option>
            </select>
          </div>

          <div class="mb-3 hidden" id="includeAllTellersContainer">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="include_all_tellers" name="include_all_tellers">
              <label class="form-check-label" for="include_all_tellers">
                Include all tellers
              </label>
            </div>
          </div>


          <div class="mb-3" id="div_employee_name">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" id="employee_name" name="employee_name">
          </div>

          <div class="mb-3">
            <label class="form-label">Daily Rate</label>
            <input type="number" class="form-control" id="daily_rate" name="daily_rate">
          </div>

          <div class="mb-3">
            <label class="form-label">Overtime Rate</label>
            <input type="number" class="form-control" id="overtime_rate" name="overtime_rate">
          </div>

          <div class="mb-3">
            <label class="form-label">Total Overtime</label>
            <input type="number" class="form-control" id="total_overtime" name="total_overtime">
          </div>

          <div class="mb-3">
            <label class="form-label">Bonus</label>
            <input type="number" class="form-control" id="bonus" name="bonus">
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
            <label class="form-label">Type</label>
            <select class="form-select select2" id="_employee_type" name="_employee_type">
              <option disabled selected>Please select</option>
              <option value="Supervisor">Supervisor</option>
              <option value="Assistant Supervisor">Assistant Supervisor</option>
              <option value="I.T.">I.T.</option>
              <option value="Teller">Teller</option>
              <option value="Teller Trainee">Teller Trainee</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" id="_employee_name" name="_employee_name">
          </div>

          <div class="mb-3">
            <label class="form-label">Daily Rate</label>
            <input type="number" class="form-control" id="_daily_rate" name="_daily_rate">
          </div>

          <div class="mb-3">
            <label class="form-label">Overtime Rate</label>
            <input type="number" class="form-control" id="_overtime_rate" name="_overtime_rate">
          </div>

          <div class="mb-3">
            <label class="form-label">Total Overtime</label>
            <input type="number" class="form-control" id="_total_overtime" name="_total_overtime">
          </div>

          <div class="mb-3">
            <label class="form-label">Bonus</label>
            <input type="number" class="form-control" id="_bonus" name="_bonus">
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
