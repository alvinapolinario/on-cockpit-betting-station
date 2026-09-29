@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Events')

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
  .layout-page .container-p-y:has(.events-app) {
    padding-top: 1rem;
  }

  .events-app {
    --navy: #0B1730;
    --navy-mid: #142445;
    --navy-end: #1A2F55;
    --meter: #8BA3C7;
    --sheet: #F4F7FB;
    --surface: #FFFFFF;
    --stroke: #E2E8F2;
    --text: #12203A;
    --muted: #6B7C99;
    --accent: #2F7BFF;
    --open: #16A34A;
    border-radius: 24px;
    overflow: hidden;
    background: var(--sheet);
    box-shadow: 0 18px 40px rgba(11, 23, 48, 0.12);
    color: var(--text);
  }

  .events-board {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 20px 22px;
    background: linear-gradient(180deg, var(--navy) 0%, var(--navy-mid) 58%, var(--navy-end) 100%);
    color: #fff;
  }

  .events-kicker {
    margin: 0;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    font-size: 13px;
    font-weight: 700;
  }

  .events-board h2 {
    margin: 2px 0 0;
    font-size: 28px;
    font-weight: 800;
    color: #fff;
  }

  .events-board p {
    margin: 4px 0 0;
    color: var(--meter);
    font-size: 13px;
  }

  .events-add {
    border: 0;
    border-radius: 16px;
    min-height: 44px;
    padding: 0 18px;
    background: var(--accent);
    color: #fff;
    font-weight: 700;
  }

  .events-sheet {
    padding: 18px 16px 22px;
  }

  .events-app .dataTables_wrapper {
    color: var(--text);
  }

  .events-app .dataTables_filter input,
  .events-app .dataTables_length select {
    border: 1px solid var(--stroke);
    border-radius: 12px;
    background: var(--surface);
    color: var(--text);
    min-height: 38px;
    padding: 0 10px;
  }

  .events-app table.dataTable {
    width: 100% !important;
    border-collapse: separate !important;
    border-spacing: 0;
  }

  .events-app table.dataTable thead th {
    background: transparent;
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    border-bottom: 1px solid var(--stroke);
    white-space: nowrap;
  }

  .events-app table.dataTable tbody td {
    background: var(--surface);
    color: var(--text);
    border-bottom: 1px solid var(--stroke);
    vertical-align: middle;
  }

  .events-app table.dataTable tbody tr:first-child td:first-child {
    border-top-left-radius: 16px;
  }

  .events-app .events-badge {
    display: inline-block;
    min-width: 84px;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    text-align: center;
  }

  .events-badge.is-pending { background: #FEF3C7; color: #92400E; }
  .events-badge.is-active { background: #DCFCE7; color: #166534; }
  .events-badge.is-done { background: #E2E8F2; color: #475569; }

  .events-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  .events-app .events-btn {
    margin: 0;
    border: 0;
    border-radius: 12px;
    min-height: 34px;
    padding: 0 10px;
    font-size: 12px;
    font-weight: 700;
  }

  .events-btn.is-tellers { background: var(--accent); color: #fff; }
  .events-btn.is-edit { background: var(--surface); color: var(--text); border: 1px solid var(--stroke) !important; }
  .events-btn.is-delete { background: #B91C1C; color: #fff; }

  .events-app .modal-content {
    border: 0;
    border-radius: 20px;
    overflow: hidden;
    background: var(--sheet);
    color: var(--text);
  }

  .events-app .modal-header,
  .modal .modal-header {
    background: linear-gradient(180deg, #0B1730 0%, #142445 100%);
    color: #fff;
    border: 0;
  }

  .events-app .modal-title,
  .modal .modal-title {
    color: #fff;
    font-weight: 700;
  }

  .events-app .btn-close {
    filter: invert(1);
  }

  .events-app .form-control,
  .events-app .form-select,
  .modal .form-control,
  .modal .form-select {
    border-radius: 12px;
    border-color: #E2E8F2;
    min-height: 42px;
  }

  .events-app .modal-footer .btn-primary,
  .events-app .modal-footer .btn-success {
    border: 0;
    border-radius: 14px;
    min-height: 42px;
    background: #2F7BFF;
    font-weight: 700;
  }

  .events-app .modal-footer .btn-secondary {
    border-radius: 14px;
    min-height: 42px;
    background: #fff;
    color: #12203A;
    border: 1px solid #E2E8F2;
  }

  @media (max-width: 767.98px) {
    .events-board { flex-direction: column; }
    .events-add { width: 100%; }
  }
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
  window.hideBootstrapModal = window.hideBootstrapModal || function (target) {
    const el = typeof target === 'string' ? document.querySelector(target) : target;
    if (el && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(el).hide();
  };
  window.showBootstrapModal = window.showBootstrapModal || function (target) {
    const el = typeof target === 'string' ? document.querySelector(target) : target;
    if (el && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(el).show();
  };
  window.toggleBootstrapModal = window.toggleBootstrapModal || function (target) {
    const el = typeof target === 'string' ? document.querySelector(target) : target;
    if (el && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(el).toggle();
  };

  init();

  var table = $('#table').DataTable({
    "stateSave": true,
    "ajax": {
      "url": "/events/list",
      "type": "POST",
      "dataSrc": "",
      "headers": {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
    },
    "columns": [
    { "data": "event_id" },
    { "data": "event_name" },
    { "data": "event_description" },
    {
        data: "event_date",
        render: function(data) {
            return new Date(data).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        }
    },
    {
        data: "event_status",
        title: "Status",
        render: function(data, type, row) {
            let badgeClass = "";

            if (data === "Pending") {
                badgeClass = "is-pending";
            } else if (data === "Active") {
                badgeClass = "is-active";
            } else if (data === "Completed") {
                badgeClass = "is-done";
            }

            return `<span class="events-badge ${badgeClass}">${data}</span>`;
        }
    },
    {
      data: "admin_cash_on_hand",
      render: function(data, type, row) {
        return formatCurrency(data);
      }
    },
    {
      data: "teller_initial_cash_on_hand",
      render: function(data, type, row) {
        return formatCurrency(data);
      }
    },
    {
      data: "prizes",
      render: function(data, type, row) {
        return formatCurrency(data);
      }
    },
    {
      data: "rd",
      render: function(data, type, row) {
        return formatCurrency(data);
      }
    },
    {
      "data": null,
      "orderable": false,
      "render": function(data, type, row) {
        return `
           <div class="events-actions">
             <button class="btn events-btn is-tellers" onclick="manageTellers(${row.event_id})">Tellers</button>
             <button class="btn events-btn is-edit" onclick="editEntry(${row.event_id})">Edit</button>
             <button class="btn events-btn is-delete" onclick="deleteEntry(${row.event_id})">Delete</button>
           </div>`;
      }
    }
    ],
  });

  $('#addEntryForm').validate({
    onfocusout: false,
    rules:
    {
      event_name:
      {
        required: true,
      },
      event_description:
      {
        required: true,
      },
      event_percentage:
      {
        required: true,
      },
      event_date:
      {
        required: true,
      },
      event_status:
      {
        required: true,
      },
      admin_cash_on_hand:
      {
        required: true,
      },
      teller_initial_cash_on_hand:
      {
        required: true,
      },
      prizes:
      {
        required: true,
      },
      rd:
      {
        required: true,
      },
    },
    submitHandler: function (form)
    {
      var formData = new FormData(form);
      $.ajax({
        type: "POST",
        url: "/events/store",
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
      _event_name:
      {
        required: true,
      },
      _event_description:
      {
        required: true,
      },
      _event_percentage:
      {
        required: true,
      },
      _event_date:
      {
        required: true,
      },
      _event_status:
      {
        required: true,
      },
      _admin_cash_on_hand:
      {
        required: true,
      },
      _teller_initial_cash_on_hand:
      {
        required: true,
      },
      _prizes:
      {
        required: true,
      },
      _rd:
      {
        required: true,
      },
    },
    submitHandler: function (form)
    {
      var formData = new FormData(form);
      $.ajax({
        type: "POST",
        url: "/events/update",
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

  $("#manageTellersForm").submit(function (e) {
  e.preventDefault();

  let eventId = $("#event_id").val();
  let selectedTellers = $(".teller-checkbox:checked").map(function() {
    return $(this).val();
  }).get();

  $.ajax({
    type: "POST",
    url: `/events/${eventId}/update-tellers`,
    data: {
      _token: $('meta[name="csrf-token"]').attr("content"),
      tellers: selectedTellers
    },
    success: function (response) {
      toastr.success(response.message);
      hideBootstrapModal('#manageTellersModal');
      table.ajax.reload(null, false);
    },
    error: function () {
      toastr.error("Failed to update tellers.");
    }
  });
});


  function editEntry(id)
  {
    $.ajax({
      type: "POST",
      dataType: 'json',
      url: "/events/find/" + id,
      async: false,
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response)
      {
        toggleBootstrapModal('#editEntryModal');
        $('#uid').val(response['event_id']);
        $('#_event_name').val(response['event_name']);
        $('#_event_description').val(response['event_description']);
        $('#_event_percentage').val(response['event_percentage']);
        $('#_event_date').val(response['event_date']);
        $('#_admin_cash_on_hand').val(response['admin_cash_on_hand']);
        $('#_teller_initial_cash_on_hand').val(response['teller_initial_cash_on_hand']);
        $('#_prizes').val(response['prizes']);
        $('#_rd').val(response['rd']);
        $('#_event_status').val(response['event_status']).trigger('change');
      },
    });
  }

  function deleteEntry(id) {
    $.ajax({
      type: "POST",
      dataType: 'json',
      url: "/events/find/" + id,
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
              url: "/events/" + id,
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

  function manageTellers(eventId) {
    $("#event_id").val(eventId);
    $("#teller-list").html('<p>Loading tellers...</p>');

    $.ajax({
      type: "POST",
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      url: `/events/${eventId}/tellers`,
      success: function (response) {
        let tellers = response.tellers;
        let assignedTellers = response.assigned_tellers;

        let html = "";
        const assigned = (assignedTellers || []).map(Number);
        tellers.forEach((teller) => {
          let isChecked = assigned.includes(Number(teller.teller_id)) ? "checked" : "";
          html += `
            <div class="form-check">
              <input class="form-check-input teller-checkbox" type="checkbox" value="${teller.teller_id}" ${isChecked}>
              <label class="form-check-label">${teller.teller_name}</label>
            </div>
          `;
        });

        $("#teller-list").html(html);
        showBootstrapModal('#manageTellersModal');
      },
      error: function () {
        toastr.error("Failed to load tellers.");
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

  function formatCurrency(amount) {
    let formatted = new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount);

    return formatted.replace(/₱\s?/, ''); // Removes ₱ symbol
  }


</script>
@endsection

@section('content')
<div class="events-app">
  <div class="events-board">
    <div>
      <p class="events-kicker">Schedule</p>
      <h2>Events</h2>
      <p>Create, activate, and assign tellers for each derby.</p>
    </div>
    <button class="events-add" data-bs-toggle="modal" data-bs-target="#addEntryModal">Add event</button>
  </div>

  <div class="events-sheet">
    <div class="table-responsive">
      <table class="table" id="table" name="table">
        <thead>
          <tr>
            <th style="width: 5%">#</th>
            <th style="width: 20%">Name</th>
            <th>Description</th>
            <th>Date</th>
            <th>Status</th>
            <th>Initial Revolving Funds</th>
            <th>Initial Teller Funds</th>
            <th>Prizes</th>
            <th>RD</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
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
            <label class="form-label">Name</label>
            <input type="text" class="form-control" id="event_name" name="event_name">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="event_description" name="event_description" rows="3"></textarea>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                  <label class="form-label">Percentage</label>
                  <input type="number" class="form-control" id="event_percentage" name="event_percentage">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" id="event_date" name="event_date">
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select class="form-select select2" id="event_status" name="event_status">
              <option disabled selected>Please select</option>
              <option value="Pending">Pending</option>
              <option value="Active">Active</option>
              <option value="Completed">Completed</option>
            </select>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Initial Revolving Fund</label>
                <input type="number" class="form-control" id="admin_cash_on_hand" name="admin_cash_on_hand">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Initial Teller Fund</label>
                <input type="number" class="form-control" id="teller_initial_cash_on_hand" name="teller_initial_cash_on_hand">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Prizes</label>
                <input type="number" class="form-control" id="prizes" name="prizes">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">RD</label>
                <input type="number" class="form-control" id="rd" name="rd">
              </div>
            </div>
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
            <label class="form-label">Name</label>
            <input type="text" class="form-control" id="_event_name" name="_event_name">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="_event_description" name="_event_description" rows="3"></textarea>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                  <label class="form-label">Percentage</label>
                  <input type="number" class="form-control" id="_event_percentage" name="_event_percentage">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" id="_event_date" name="_event_date">
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select class="form-select select2" id="_event_status" name="_event_status">
              <option disabled selected>Please select</option>
              <option value="Pending">Pending</option>
              <option value="Active">Active</option>
              <option value="Completed">Completed</option>
            </select>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Initial Revolving Fund</label>
                <input type="number" class="form-control" id="_admin_cash_on_hand" name="_admin_cash_on_hand">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Initial Teller Fund</label>
                <input type="number" class="form-control" id="_teller_initial_cash_on_hand" name="_teller_initial_cash_on_hand">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Prizes</label>
                <input type="number" class="form-control" id="_prizes" name="_prizes">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">RD</label>
                <input type="number" class="form-control" id="_rd" name="_rd">
              </div>
            </div>
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

<div class="modal fade" id="manageTellersModal" tabindex="-1" aria-labelledby="manageTellersModal" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="manageTellersForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Manage Tellers for Event</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>Tellers who have already placed bets cannot be removed. It is recommended to disable their accounts via <b> Accounts > Tellers</b> instead.
          </p>
          <input type="hidden" id="event_id" name="event_id">
          <div class="mb-3">
            <label class="form-label">Select Tellers</label>
            <div id="teller-list"></div> <!-- Dynamic Teller List Here -->
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>


@endsection
