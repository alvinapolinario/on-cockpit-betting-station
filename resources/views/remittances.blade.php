@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Remittances')

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
      "url": "/remittances/tellers/list",
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
    { "data": "teller_name" },
    {
      "data": null,
      "render": data => {
        const count = data.denom_1000;
        const total = count * 1000;
        return `${count} × 1,000 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": data => {
        const count = data.denom_500;
        const total = count * 500;
        return `${count} × 500 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": data => {
        const count = data.denom_200;
        const total = count * 200;
        return `${count} × 200 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": data => {
        const count = data.denom_100;
        const total = count * 100;
        return `${count} × 100 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": data => {
        const count = data.denom_50;
        const total = count * 50;
        return `${count} × 50 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": data => {
        const count = data.denom_20;
        const total = count * 20;
        return `${count} × 20 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": data => {
        const count = data.denom_10;
        const total = count * 10;
        return `${count} × 10 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": data => {
        const count = data.denom_5;
        const total = count * 5;
        return `${count} × 5 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": data => {
        const count = data.denom_1;
        const total = count * 1;
        return `${count} × 1 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": function(data, type, row) {
        const total = (data.denom_1000 * 1000) + (data.denom_500 * 500) + (data.denom_200 * 200) +
        (data.denom_100 * 100) + (data.denom_50 * 50) + (data.denom_20 * 20) +
        (data.denom_10 * 10) + (data.denom_5 * 5) + (data.denom_1 * 1);
        return `<strong class="text-success">${formatCurrency(total)}</strong>`;
      }
    },
    {
      "data": null,
      "render": function(data, type, row) {
        return `<strong class="text-success">${formatCurrency(row.teller_balance)}</strong>`;
      }
    },
    {
      "data": null,
      "render": function(data, type, row) {
        const total = (data.denom_1000 * 1000) + (data.denom_500 * 500) + (data.denom_200 * 200) +
        (data.denom_100 * 100) + (data.denom_50 * 50) + (data.denom_20 * 20) +
        (data.denom_10 * 10) + (data.denom_5 * 5) + (data.denom_1 * 1);
        return `<strong class="text-warning">${formatCurrency(total - row.teller_balance)}</strong>
          <br>
      <button class="btn btn-sm btn-danger btn-xs mt-1" onclick="openShortsModal(${row.teller_remittance_id}, 'teller')">Add Short</button>
        `;
      }
    },
    {
      "data": null,
      "orderable": false,
      "render": data => `
      <button class="btn btn-sm btn-secondary" onclick="editEntry(${data.teller_remittance_id})">Edit</button>
    `
    }
    ],


  });


  table.on('draw', function () {
    let sum = {
      1000: 0, 500: 0, 200: 0, 100: 0, 50: 0, 20: 0, 10: 0, 5: 0, 1: 0,
      total: 0,
      balance: 0,
      difference: 0
    };

    table.rows({ page: 'current' }).data().each(function (data) {
      sum[1000] += data.denom_1000;
      sum[500] += data.denom_500;
      sum[200] += data.denom_200;
      sum[100] += data.denom_100;
      sum[50] += data.denom_50;
      sum[20] += data.denom_20;
      sum[10] += data.denom_10;
      sum[5] += data.denom_5;
      sum[1] += data.denom_1;

      const total = (data.denom_1000 * 1000) + (data.denom_500 * 500) + (data.denom_200 * 200) +
      (data.denom_100 * 100) + (data.denom_50 * 50) + (data.denom_20 * 20) +
      (data.denom_10 * 10) + (data.denom_5 * 5) + (data.denom_1 * 1);
      sum.total += total;
      sum.balance += data.teller_balance;
      sum.difference += total - data.teller_balance;
    });

    // Update footer values
    for (const key of [1000, 500, 200, 100, 50, 20, 10, 5, 1]) {
      $('#sum_' + key).text(sum[key]);
    }

    $('#sum_total').html(`<strong class="text-success h4">${formatCurrency(sum.total)}</strong>`);
    $('#sum_balance').html(`<strong class="text-success h4">${formatCurrency(sum.balance)}</strong>`);
    $('#sum_difference').html(`<strong class="text-warning h4">${formatCurrency(sum.difference)}</strong>`);
  });


  var table_admin = $('#table_admin').DataTable({
    "stateSave": true,
    "ajax": {
      "url": "/remittances/admins/list",
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
    {
      "data": null,
      "render": data => {
        const count = data.denom_1000;
        const total = count * 1000;
        return `${count} × 1,000 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": data => {
        const count = data.denom_500;
        const total = count * 500;
        return `${count} × 500 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": data => {
        const count = data.denom_200;
        const total = count * 200;
        return `${count} × 200 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": data => {
        const count = data.denom_100;
        const total = count * 100;
        return `${count} × 100 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": data => {
        const count = data.denom_50;
        const total = count * 50;
        return `${count} × 50 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": data => {
        const count = data.denom_20;
        const total = count * 20;
        return `${count} × 20 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": data => {
        const count = data.denom_10;
        const total = count * 10;
        return `${count} × 10 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": data => {
        const count = data.denom_5;
        const total = count * 5;
        return `${count} × 5 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": data => {
        const count = data.denom_1;
        const total = count * 1;
        return `${count} × 1 = <strong class="text-primary">${formatCurrency(total)}</strong>`;
      }
    },

    {
      "data": null,
      "render": function(data, type, row) {
        const total = (data.denom_1000 * 1000) + (data.denom_500 * 500) + (data.denom_200 * 200) +
        (data.denom_100 * 100) + (data.denom_50 * 50) + (data.denom_20 * 20) +
        (data.denom_10 * 10) + (data.denom_5 * 5) + (data.denom_1 * 1);
        return `<strong class="text-success">${formatCurrency(total)}</strong>`;
      }
    },
    {
      "data": null,
      "render": function(data, type, row) {
        return `<strong class="text-success">${formatCurrency(row.cash_on_hand)}</strong>`;
      }
    },
    {
      "data": null,
      "render": function(data, type, row) {
        const total = (data.denom_1000 * 1000) + (data.denom_500 * 500) + (data.denom_200 * 200) +
        (data.denom_100 * 100) + (data.denom_50 * 50) + (data.denom_20 * 20) +
        (data.denom_10 * 10) + (data.denom_5 * 5) + (data.denom_1 * 1);
        return `<strong class="text-warning">${formatCurrency(total - row.cash_on_hand)}</strong>
        <br>
      <button class="btn btn-sm btn-danger btn-xs mt-1" onclick="openShortsModal(${row.admin_remittance_id}, 'admin')">Add Short</button>`;
      }
    },
    {
      "data": null,
      "orderable": false,
      "render": data => `
      <button class="btn btn-sm btn-secondary" onclick="editEntryAdmin(${data.admin_remittance_id})">Edit</button>
    `
    }
    ],


  });

  $('#editEntryForm').validate({
    onfocusout: false,
    rules:
    {
      _denom_1000:
      {
        required: true,
      },
      _denom_500:
      {
        required: true,
      },
      _denom_200:
      {
        required: true,
      },
      _denom_100:
      {
        required: true,
      },
      _denom_50:
      {
        required: true,
      },
      _denom_20:
      {
        required: true,
      },
      _denom_10:
      {
        required: true,
      },
      _denom_5:
      {
        required: true,
      },
      _denom_1:
      {
        required: true,
      },
    },
    submitHandler: function (form)
    {
      var formData = new FormData(form);
      $.ajax({
        type: "POST",
        url: "/remittances/teller/update",
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


  $('#editEntryAdminForm').validate({
    onfocusout: false,
    rules:
    {
      admin_denom_1000:
      {
        required: true,
      },
      admin_denom_500:
      {
        required: true,
      },
      admin_denom_200:
      {
        required: true,
      },
      admin_denom_100:
      {
        required: true,
      },
      admin_denom_50:
      {
        required: true,
      },
      admin_denom_20:
      {
        required: true,
      },
      admin_denom_10:
      {
        required: true,
      },
      admin_denom_5:
      {
        required: true,
      },
      admin_denom_1:
      {
        required: true,
      },
    },
    submitHandler: function (form)
    {
      var formData = new FormData(form);
      $.ajax({
        type: "POST",
        url: "/remittances/admin/update",
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
            hideBootstrapModal('#editEntryAdminModal');
            table_admin.ajax.reload(null, false);
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
      url: "/remittances/teller/find/" + id,
      async: false,
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response)
      {
        toggleBootstrapModal('#editEntryModal');
        $('#uid').val(response['teller_remittance_id']);
        $('#_employee_name').val(response['teller_name']);
        $('#_denom_1000').val(response['denom_1000']);
        $('#_denom_500').val(response['denom_500']);
        $('#_denom_200').val(response['denom_200']);
        $('#_denom_100').val(response['denom_100']);
        $('#_denom_50').val(response['denom_50']);
        $('#_denom_20').val(response['denom_20']);
        $('#_denom_10').val(response['denom_10']);
        $('#_denom_5').val(response['denom_5']);
        $('#_denom_1').val(response['denom_1']);
        calculateAllSubtotals();
      },
    });
  }

  function editEntryAdmin(id)
  {
    $.ajax({
      type: "POST",
      dataType: 'json',
      url: "/remittances/admin/find/" + id,
      async: false,
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response)
      {
        toggleBootstrapModal('#editEntryAdminModal');
        $('#admin_uid').val(response['admin_remittance_id']);
        $('#admin_denom_1000').val(response['denom_1000']);
        $('#admin_denom_500').val(response['denom_500']);
        $('#admin_denom_200').val(response['denom_200']);
        $('#admin_denom_100').val(response['denom_100']);
        $('#admin_denom_50').val(response['denom_50']);
        $('#admin_denom_20').val(response['denom_20']);
        $('#admin_denom_10').val(response['denom_10']);
        $('#admin_denom_5').val(response['denom_5']);
        $('#admin_denom_1').val(response['denom_1']);
        calculateAllSubtotals("admin");
      },
    });
  }


  $('#generateListBtn').on('click', function () {
    let eventId = $('#event_id').val();

    if (!eventId) {
      toastr.warning('Please select an event first.');
      return;
    }

    Swal.fire({
      title: 'Generate Remittance List?',
      text: "This will generate remittance entries for the selected event.",
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, generate it!',
      cancelButtonText: 'Cancel',
      customClass: {
        confirmButton: 'btn btn-success me-2',
        cancelButton: 'btn btn-label-secondary'
      },
      buttonsStyling: false
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: `/remittances/generate/${eventId}`,
          type: 'POST',
          headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          },
          beforeSend: function () {
            toastr.info('Generating remittance list...');
          },
          success: function (response) {
            toastr.success('Remittance list generated successfully!');
            table.ajax.reload();
          },
          error: function () {
            toastr.error('Failed to generate remittance list.');
          }
        });
      }
    });
  });




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
    table_admin.ajax.reload();
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

  function updateSubtotal(el) {
    const id = el.id;
    const denom = parseInt(id.replace(/[^\d]/g, ''));
    const qty = parseInt(el.value) || 0;
    const total = denom * qty;

    // Auto-detect prefix (either 'admin' or '')
    const isAdmin = id.startsWith('admin_');
    const prefix = isAdmin ? 'admin_' : '';

    const targetId = `${prefix}subtotal_${denom}`;
    const target = document.getElementById(targetId);
    if (target) {
      target.innerText = total.toLocaleString();
    }
  }


  function calculateAllSubtotals(prefix = '') {
  const denominations = [1000, 500, 200, 100, 50, 20, 10, 5, 1];

  denominations.forEach(denom => {
    let id = prefix ? `${prefix}_denom_${denom}` : `_denom_${denom}`; // <-- handles underscore for teller
    const input = document.getElementById(id);
    if (input) updateSubtotal(input, prefix);
  });
}




  $('#editEntryModal').on('hidden.bs.modal', function()
  {
    $("#editEntryForm").trigger("reset");
  });

  $('#editEntryAdminModal').on('hidden.bs.modal', function()
  {
    $("#editEntryAdminForm").trigger("reset");
  });


  let currentRemittanceId = null;
let currentType = null; // 'teller' or 'admin'

function openShortsModal(remittanceId, type) {
  currentRemittanceId = remittanceId;
  currentType = type;

  showBootstrapModal('#shortsModal');
  fetchShorts(remittanceId, type);
}

function fetchShorts(remittanceId, type) {
  $('#shortList').html('Loading...');

  $.ajax({
    url: `/remittances/${type}-shorts/list/${remittanceId}`,
    method: 'GET',
    success: function (shorts) {
      const html = shorts.map(short => `
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <div>
            <strong>${formatCurrency(short.short_amount)}</strong> — ${short.short_remarks || ''}
          </div>
          <button class="btn btn-sm btn-danger" onclick="deleteShort(${short[type + '_short_id']}, '${type}')">Delete</button>
        </li>
      `).join('');

      $('#shortList').html(html || '<li class="list-group-item">No shorts recorded.</li>');
    }
  });
}

$('#shortForm').submit(function (e) {
  e.preventDefault();
  const data = {
    short_amount: $(this).find('[name=short_amount]').val(),
    short_remarks: $(this).find('[name=short_remarks]').val(),
    [`${currentType}_remittance_id`]: currentRemittanceId
  };

  $.ajax({
    url: `/remittances/${currentType}-shorts/store`,
    method: 'POST',
    data,
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    },
    success: function () {
      fetchShorts(currentRemittanceId, currentType);
      $('#shortForm')[0].reset();
      toastr.success('Short added');
    }
  });
});

function deleteShort(id, type) {
  $.ajax({
    url: `/remittances/${type}-shorts/delete/${id}`,
    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    },
    success: function () {
      fetchShorts(currentRemittanceId, type);
      toastr.success('Short deleted');
    }
  });
}



</script>
@endsection

@section('content')

<div class="container-fluid">
  <h4 class="py-3 breadcrumb-wrapper mb-4">
    <span class="text-muted fw-light">Menu /</span> <span class="text-muted fw-light">Accounts /</span> Remittances
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


        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-success" id="generateListBtn">Generate List</button>

        </div>


      </div>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-body">
            <h3>Tellers</h3>
            <div class="table-responsive">
              <table class="table table-striped" id="table" name="table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>1000</th>
                    <th>500</th>
                    <th>200</th>
                    <th>100</th>
                    <th>50</th>
                    <th>20</th>
                    <th>10</th>
                    <th>5</th>
                    <th>1</th>
                    <th><strong>Total</strong></th>
                    <th><strong>Cash on Hand</strong></th>
                    <th><strong>Difference</strong></th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>

                </tbody>
                <tfoot>
                  <tr>
                    <th>Total</th>
                    <th id="sum_1000">0</th>
                    <th id="sum_500">0</th>
                    <th id="sum_200">0</th>
                    <th id="sum_100">0</th>
                    <th id="sum_50">0</th>
                    <th id="sum_20">0</th>
                    <th id="sum_10">0</th>
                    <th id="sum_5">0</th>
                    <th id="sum_1">0</th>
                    <th id="sum_total">0</th>
                    <th id="sum_balance">0</th>
                    <th id="sum_difference">0</th>
                    <th></th>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row mt-3">
      <div class="col-md-12">
        <div class="card">
          <div class="card-body">
            <h3>Admins</h3>
            <div class="table-responsive">
              <table class="table table-striped" id="table_admin" name="table_admin">
                <thead>
                  <tr>
                    <th>1000</th>
                    <th>500</th>
                    <th>200</th>
                    <th>100</th>
                    <th>50</th>
                    <th>20</th>
                    <th>10</th>
                    <th>5</th>
                    <th>1</th>
                    <th><strong>Total</strong></th>
                    <th><strong>Cash on Hand</strong></th>
                    <th><strong>Difference</strong></th>
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
            <input type="text" class="form-control" id="_employee_name" name="_employee_name" readonly>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 1000</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="_denom_1000" name="_denom_1000" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="subtotal_1000">0</span></span>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 500</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="_denom_500" name="_denom_500" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="subtotal_500">0</span></span>
                </div>
              </div>
            </div>

          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 200</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="_denom_200" name="_denom_200" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="subtotal_200">0</span></span>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 100</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="_denom_100" name="_denom_100" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="subtotal_100">0</span></span>
                </div>
              </div>
            </div>
          </div>


          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 50</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="_denom_50" name="_denom_50" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="subtotal_50">0</span></span>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 20</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="_denom_20" name="_denom_20" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="subtotal_20">0</span></span>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 10</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="_denom_10" name="_denom_10" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="subtotal_10">0</span></span>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 5</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="_denom_5" name="_denom_5" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="subtotal_5">0</span></span>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 1</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="_denom_1" name="_denom_1" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="subtotal_1">0</span></span>
                </div>
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

<div class="modal fade" id="editEntryAdminModal" tabindex="-1" aria-labelledby="editEntryAdminModal" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="editEntryAdminForm" name="editEntryAdminForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Edit Entry</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">


          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 1000</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="admin_denom_1000" name="admin_denom_1000" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="admin_subtotal_1000">0</span></span>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 500</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="admin_denom_500" name="admin_denom_500" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="admin_subtotal_500">0</span></span>
                </div>
              </div>
            </div>

          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 200</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="admin_denom_200" name="admin_denom_200" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="admin_subtotal_200">0</span></span>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 100</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="admin_denom_100" name="admin_denom_100" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="admin_subtotal_100">0</span></span>
                </div>
              </div>
            </div>
          </div>


          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 50</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="admin_denom_50" name="admin_denom_50" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="admin_subtotal_50">0</span></span>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 20</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="admin_denom_20" name="admin_denom_20" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="admin_subtotal_20">0</span></span>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 10</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="admin_denom_10" name="admin_denom_10" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="admin_subtotal_10">0</span></span>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 5</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="admin_denom_5" name="admin_denom_5" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="admin_subtotal_5">0</span></span>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"># of 1</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="admin_denom_1" name="admin_denom_1" oninput="updateSubtotal(this)">
                  <span class="input-group-text">= ₱<span id="admin_subtotal_1">0</span></span>
                </div>
              </div>
            </div>

          </div>





        </div>
        <div class="modal-footer">
          <input type="hidden" id="admin_uid" name="admin_uid">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-success">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="shortsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Short Entries</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <form id="shortForm">
          <div class="row">
            <div class="col-md-4">
              <input type="number" class="form-control" name="short_amount" placeholder="Short Amount" required>
            </div>
            <div class="col-md-6">
              <input type="text" class="form-control" name="short_remarks" placeholder="Remarks">
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-danger w-100">Add</button>
            </div>
          </div>
        </form>

        <hr>

        <ul class="list-group mt-3" id="shortList"></ul>

      </div>
    </div>
  </div>
</div>

@endsection
