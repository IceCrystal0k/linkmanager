<table class="table table-bordered align-middle table-row-dashed fs-6 gy-5">
    <thead>
        <tr>
            <th>{{ __('tables.Id') }}</th>
            <th>{{ __('tables.Name') }}</th>
            <th>{{ __('tables.Email') }}</th>
            <th>{{ __('tables.Role') }}</th>
            <th>{{ __('tables.UpdatedAt') }}</th>
            <th>{{ __('tables.Google') }}</th>
            <th>{{ __('tables.Facebook') }}</th>
            <th>{{ __('tables.Status') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $row)
        <tr>
            <td>{{ $row->id}}</td>
            <td>{{ $row->full_name}}</td>
            <td>{{ $row->email}}</td>
            <td>{{ $row->role_name}}</td>
            <td>{{ $row->updated_date}}</td>
            <td>{{ $row->google}}</td>
            <td>{{ $row->facebook}}</td>
            <td>{{ $row->status_name}}</td>
        </tr>
        @endforeach
    </tbody>
</table>
