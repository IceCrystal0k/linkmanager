<table class="table table-bordered align-middle table-row-dashed fs-6 gy-5">
    <thead>
        <tr>
            <th>{{ __('tables.Id') }}</th>
            <th>{{ __('tables.Category') }}</th>
            <th>{{ __('tables.Name') }}</th>
            <th>{{ __('tables.Url') }}</th>
            <th>{{ __('tables.Rating') }}</th>
            <th>{{ __('tables.Visits') }}</th>
            <th>{{ __('tables.Status') }}</th>
            <th>{{ __('tables.CreatedAt') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $row)
        <tr>
            <td>{{ $row->id}}</td>
            <td>{{ $row->category_name}}</td>
            <td>{{ $row->name}}</td>
            <td>{{ $row->url}}</td>
            <td>{{ $row->rating}}</td>
            <td>{{ $row->visits}}</td>
            <td>{{ $row->status_name}}</td>
            <td>{{ $row->created_at}}</td>
        </tr>
        @endforeach
    </tbody>
</table>
