@extends('layouts.master')

@section('title')
    {{ __('Subdomain Status') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('Subdomain Health Status') }}
            </h3>
        </div>

        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <h4 class="card-title">{{ __('All Schools Subdomain Status') }}</h4>
                            <button class="btn btn-primary" onclick="if(typeof refreshStatus === 'function'){refreshStatus();}">
                                <i class="fa fa-refresh"></i> {{ __('Refresh') }}
                            </button>
                        </div>
                        
                        <div id="status-container">
                            <div class="text-center">
                                <div class="spinner-border" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <p class="mt-2">{{ __('Loading subdomain status...') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
<script>
$(document).ready(function() {
    loadSubdomainStatus();
});

function loadSubdomainStatus() {
    $.ajax({
        url: '{{ route("schools.subdomain-health-all") }}',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                displayStatus(response.data);
            } else {
                showError('Failed to load subdomain status');
            }
        },
        error: function() {
            showError('Failed to load subdomain status');
        }
    });
}

function displayStatus(data) {
    let html = '<div class="table-responsive">';
    html += '<table class="table table-striped">';
    html += '<thead><tr>';
    html += '<th>{{ __("School Name") }}</th>';
    html += '<th>{{ __("Domain") }}</th>';
    html += '<th>{{ __("Full URL") }}</th>';
    html += '<th>{{ __("Status") }}</th>';
    html += '<th>{{ __("Actions") }}</th>';
    html += '</tr></thead><tbody>';
    
    data.forEach(function(item) {
        const health = item.health;
        const statusClass = getStatusClass(health.status);
        const statusIcon = getStatusIcon(health.status);
        
        html += '<tr>';
        html += '<td>' + item.school_name + '</td>';
        html += '<td>' + (health.domain || '{{ __("Not set") }}') + '</td>';
        html += '<td><a href="' + (health.full_url || '#') + '" target="_blank">' + (health.full_url || 'N/A') + '</a></td>';
        html += '<td><span class="badge ' + statusClass + '">' + statusIcon + ' ' + health.message + '</span></td>';
        html += '<td>';
        html += '<button class="btn btn-sm btn-info" onclick="if(typeof checkSingleStatus === \'function\'){checkSingleStatus(' + item.school_id + ');} ">';
        html += '<i class="fa fa-refresh"></i> {{ __("Check") }}';
        html += '</button>';
        html += '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table></div>';
    
    $('#status-container').html(html);
}

function getStatusClass(status) {
    switch(status) {
        case 'healthy': return 'badge-success';
        case 'unhealthy': return 'badge-danger';
        case 'not_configured': return 'badge-warning';
        default: return 'badge-secondary';
    }
}

function getStatusIcon(status) {
    switch(status) {
        case 'healthy': return '✅';
        case 'unhealthy': return '❌';
        case 'not_configured': return '⚠️';
        default: return '❓';
    }
}

function checkSingleStatus(schoolId) {
    $.ajax({
        url: '/schools/' + schoolId + '/subdomain-health',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                showSuccess('Status updated for school ID: ' + schoolId);
                loadSubdomainStatus(); // Refresh the table
            } else {
                showError('Failed to check status for school ID: ' + schoolId);
            }
        },
        error: function() {
            showError('Failed to check status for school ID: ' + schoolId);
        }
    });
}

function refreshStatus() {
    $('#status-container').html(`
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-2">{{ __('Refreshing subdomain status...') }}</p>
        </div>
    `);
    loadSubdomainStatus();
}

function showSuccess(message) {
    // You can use your existing toast notification system
    alert('Success: ' + message);
}

function showError(message) {
    // You can use your existing toast notification system
    alert('Error: ' + message);
}
</script>
@endsection 