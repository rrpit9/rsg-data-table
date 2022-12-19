<div class="card" id="{{ $uniqueID }}">
    {{-- <h6 class="card-header"></h6> --}}
    
    {{-- Table Header --}}
    <div class="row">
        <div class="col-sm-12 col-md-4 justify-content-center">
            <div class="dataTables_length">
                <label>
                    @lang('data-table::messages.show')
                    <select aria-controls="DataTables_Table_2" class="form-select dt--show-entries-options">
                        @foreach($rowPerPageOptions as $rowPerPageOptions)
                            <option value="{{ $rowPerPageOptions }}"{!! $rowPerPageOptions == $rowPerPage ? ' selected="selected"' : '' !!}>{{ $rowPerPageOptions }}</option>
                        @endforeach
                    </select>
                    @lang('data-table::messages.entries')
                </label>
            </div>
        </div>
        
        <div class="col-sm-12 col-md-4 justify-content-md-end">
            @if( count($downloadOptions) > 0 && (config('data-table.download_csv') || config('data-table.download_excel') || config('data-table.download_pdf')))
                <a class="{{ config('data-table.download_btn') }} dt--download" href="javascript:void(0);" title="@lang('data-table::messages.download')"><i class="bx bx-download"></i></a>
            @endif
            <a class="{{ config('data-table.refresh_btn') }} dt--reload-data" href="javascript:void(0);" title="@lang('data-table::messages.reload_data')"><i class="bx bx-refresh"></i></a>
        </div>

        <div class="col-sm-12 col-md-4 dt--global-search">
            <div id="DataTables_Table_2_filter" class="dataTables_filter">
                <label>Search:<input type="search" name="search" class="form-control" placeholder="@lang('data-table::messages.search_placeholder')" aria-controls="DataTables_Table_2" /></label>
            </div>
        </div>
    </div>

    <div class="card-datatable table-responsive">
        <div class="dataTables_wrapper dt-bootstrap5">
            <div class="dt--table">
                <table class="dt-row-grouping table border-top dataTable dtr-column" style="width: 100%">
                    <thead>
                        <tr class="text-nowrap">
                            @foreach($columns as $id => $label)
                                @php
                                $haveSorting = in_array($id, $sortableColumns);
                                $sortDirection = ($haveSorting && $id == $orderBy['sort'] ? strtolower($orderBy['sort_direction']) : false);
                                $sortIconClass = $sortDirection ? ($sortDirection == 'asc' ? 'glyphicon-sort-by-attributes' : 'glyphicon-sort-by-attributes-alt') : 'glyphicon-sort';
                                @endphp
                                <th data-column="{{ $id }}" class="column-{{ $id }} {{ $haveSorting ? 'dt--sorting' : '' }}{{ $sortDirection ? ' dt--sorting-' . $orderBy['sort_direction'] : '' }}" {!! $haveSorting ? ' style="cursor: pointer;"' : '' !!}>
                                    {!! $label !!}
                                    @if($haveSorting)
                                        <i class="glyphicon {{ $sortIconClass }} pull-right"></i>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                        @if( count($searchableColumns) > 0 )
                            <tr>
                                @foreach ($columns as $id => $title)
                                    @if ( isset($searchableColumns[$id]) )
                                        <th class="column-search-{{ $id }}">
                                            @if( $searchableColumns[$id] == 'integer' )
                                                <input name="{{ $id }}" class="form-control" type="number" autocomplete="off" />
                                            @elseif( is_array($searchableColumns[$id]) )
                                                <select name="{{ $id }}" class="form-control input-sm">
                                                    <option value="">All</option>
                                                    @foreach( $searchableColumns[$id] as $val => $title )
                                                        <option value="{{ $val }}">{{ $title }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif( $searchableColumns[$id] == 'date' )
                                                <input name="{{ $id }}" placeholder="mm/dd/yyyy" class="form-control date-picker" type="text" autocomplete="off" />
                                            @elseif( $searchableColumns[$id] == 'daterange' )
                                                <input name="{{ $id }}" placeholder="" class="form-control daterange-picker" type="text" autocomplete="off" />
                                            @else
                                                <input name="{{ $id }}" class="form-control" type="text" autocomplete="off" />
                                            @endif
                                        </th>
                                    @else
                                        <th class="column-search-{{ $id }}"></th>
                                    @endif
                                @endforeach
                            </tr>
                        @endif
                    </thead>
                    <tbody></tbody>
                    <tfoot>
                        <tr>
                            <td colspan="{{ count($columns) / 2 }}"><div class="pull-left dt--pagination-info" style="margin-top: 5px;" data-trans="@lang('data-table::messages.pagination_info')">@lang('data-table::messages.pagination_info', ['from' => 0, 'to' => 0, 'total' => 0])</div></td>
                            <td colspan="{{ count($columns)}}">
                                <div class="pull-right" style="margin-top: 5px; margin-right: 10px">
                                    @lang('data-table::messages.go_to_page') <input type="number" min="1" max="1" value="1" size="3" width="10px" class="text-center dt--go-to-page"> @lang('data-table::messages.of') <span class="dt--total-pages">0</span>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
        $('#{{ $uniqueID }}').dataTable({
            uniqueID: '{{ $uniqueID }}',
            ajaxURI: '{{ $ajaxDataURI }}',
            columns: JSON.parse('{!! json_encode($columns) !!}'),
            rowPerPage: '{{ $rowPerPage }}',
            sortBy: '{{ $orderBy['sort'] }}',
            sortByDirection: '{{ $orderBy['sort_direction'] }}',
            orderBy: JSON.parse('{!! json_encode($orderBy) !!}'),
            loadingTxt: '{!! config('data-table.loading_txt') !!}'
        });
    });
</script>
