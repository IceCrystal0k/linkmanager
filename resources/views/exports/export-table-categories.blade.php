<table class="table table-bordered align-middle table-row-dashed fs-6 gy-5">
    <thead>
        <tr>
            <th>{{ __('tables.Id') }}</th>
            <th>{{ __('tables.Parent') }}</th>
            <th>{{ __('tables.Name') }}</th>
            <th>{{ __('tables.OrderIndex') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $row)
        <tr>
            <td>{{ $row->id}}</td>
            <td>{{ $row->parent_id}}</td>
            <td>{{ $row->name}}</td>
            <td>{{ $row->order_index}}</td>
        </tr>
        @endforeach
    </tbody>
</table>
