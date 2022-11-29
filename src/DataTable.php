<?php
namespace Srg\DataTable;

abstract class DataTable
{

    /**
     * Define unique table id
     * @var mixed $rowPerPage
     */
    public $uniqueID;


    /**
     * Define how many rows you want to display on a page
     * @var int $rowPerPage
     */
    public $rowPerPage;


    /**
     * Define row per page dropdown options
     * @var array $rowPerPageOptions
     */
    public $rowPerPageOptions;


    /**
     * Define mysql column name which you want to default on sorting
     * @var string $defaultSortBy
     */
    public $sortBy;

    /**
     * Define default soring direction
     * Example: ASC | DESC
     * @var string $defaultSortBy
     */
    public $sortByDirection;

    /**
     * Set debug true to get mysql query in XHR request for json
     * @var mixed $rowPerPage
     */
    public $debug;

    /**
     * Get HTML of Data Table
     * @param array $params
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public final static function toHTML( array $params = [] )
    {
        $self = new static($params);
        $uniqueID = $self->uniqueID ?: 'unique_id_'.time();
        $ajaxDataURI = route( 'dataTableJSON', [encrypt(get_class($self)), 'extra_params' => encrypt(json_encode($params))] );
        $columns = $self->columns();
        $sortableColumns = $self->sortableColumns();
        $searchableColumns = $self->searchableColumns();
        $filters = $self->filters();
        $downloadOptions = $self->downloadableColumns();

        $rowPerPage = $self->rowPerPage ?: config('data-table.row_per_page');
        $rowPerPageOptions = $self->rowPerPageOptions ?: config('data-table.row_per_page_options');

        if(!in_array($rowPerPage, $rowPerPageOptions)) {
            $rowPerPageOptions[] = $rowPerPage;
        }

        $orderBy = ['sort' => $self->sortBy ?: '', 'sort_direction' => $self->sortBy ? ($self->sortByDirection ?: 'desc') : ''];

        return view('data-table::layout', compact('uniqueID', 'ajaxDataURI', 'columns', 'sortableColumns', 'searchableColumns', 'filters', 'downloadOptions', 'rowPerPage', 'rowPerPageOptions', 'orderBy'));
    }
    /**
     * Get JSON of Data Table
     */
    public final static function toJSON()
    {
        $returnData = ['total_pages' => 1, 'current_page' => 1, 'from' => 0, 'to' => 0, 'total_records' => 0, 'data' => []];

        request()->merge([
           'page' => ( !is_numeric(request()->get('page')) || request()->get('page') < 1 ) ? 1 : request()->get('page'),
           'limit' => ( !is_numeric(request()->get('limit')) || request()->get('limit') < 1 ) ? 10 : request()->get('limit'),
           'extra_params' => json_decode(decrypt(request()->get('extra_params')), true)
        ]);

        $self = new static();
        $queryBuilder = $self->resource();

        if(request()->get('sort_by')) {
            $queryBuilder->orderBy($self->getSqlColumn(request()->get('sort_by')), ( request()->get('sort_by_direction') == 'desc' ? 'desc' : 'asc') );
        }

        if(request()->get('action') == 'search-columns' && !empty(request()->get('filters', []))) {
            $self->ajaxSearchColumns($queryBuilder);
        } elseif(request()->get('action') == 'search' && !empty(request()->get('filters', ''))) {
            $self->ajaxGlobalSearch($queryBuilder);
        }

        return $self->response($queryBuilder, $returnData);
    }

    public function response(&$queryBuilder, &$returnData)
    {
        if(request()->get('download') == 'csv') {
            $downloadColumns = $this->downloadableColumns();
            $data = $queryBuilder;
            $this->exportCsv($queryBuilder);
        } else {
            $data = $queryBuilder->paginate( request()->get('limit') )->toArray();
            if($this->debug == true) {
                $raw = function ($sql, $bindings) {
                    $flat = array_flatten($bindings);
                    foreach ($flat as $binding) {
                        $binded = $binding instanceof \DateTime ? $binding->format('Y-m-d H:i:s') : $binding;
                        $binded = is_numeric($binded) ? $binded : "'{$binded}'";
                        $sql = preg_replace('/\?/', $binded, $sql, 1);
                    }
                    return $sql;
                };
                $returnData['sql'] = $queryBuilder->toSql();
                $returnData['bindings'] = $queryBuilder->getBindings();
                $returnData['raw'] = $raw($queryBuilder->toSql(), $queryBuilder->getBindings());
            }
            $returnData['data'] = $this->processRows($data['data']);
            $returnData['total_pages'] = isset($data['last_page']) ? $data['last_page'] : 1;
            $returnData['current_page'] = isset($data['current_page']) ? $data['current_page'] : 1;
            $returnData['from'] = isset($data['from']) ? $data['from'] : 0;
            $returnData['to'] = isset($data['to']) ? $data['to'] : 0;
            $returnData['total_records'] = isset($data['total']) ? $data['total'] : 0;
        }

        return $returnData;
    }

    private function exportCsv($query)
    {
        $filename = $this->uniqueID . time().".csv";
        header('Content-type: application/csv');
        header('Content-Disposition: attachment; filename='.$filename);

        $fp = fopen('php://output', 'w');
        $downloadColumns = $this->downloadableColumns();
        fputcsv($fp, array_values($downloadColumns));
        
        $query->chunk(2500, function($rows) use ($fp,$downloadColumns)
        {
            foreach ($rows as $row)
            {
                $tmpArray = [];
                foreach($downloadColumns as $downloadColumnKey => $downloadColumnVal) {
                    if(method_exists($this, "getColumn".studly_case($downloadColumnKey))) {
                        $val = call_user_func_array(array($this, "getColumn".studly_case($downloadColumnKey)), array($row, 'download'));
                    } else if( isset($row->$downloadColumnKey) ) {
                        $val = $row->$downloadColumnKey;
                    } else {
                        $val = "";
                    }
                    $tmpArray[] = $val;
                }

                fputcsv($fp, array_values($tmpArray));
            }
        });

        fclose($fp);
        exit;
    }

    public function ajaxGlobalSearch(&$queryBuilder)
    {
        $queryBuilder->where(function($query){
            $search_value = request()->get('filters');
            $searchableColumns = $this->searchableColumns();
            foreach($searchableColumns as $key => $keyType) {
                if(method_exists($this, "customSearchableColumns") && array_key_exists($key, $this->customSearchableColumns()) ) {
                    if(method_exists($this, "search".studly_case($key))) {
                       $result = call_user_func_array(array($this, "search".studly_case($key)), array($search_value));
                       $searchable_array = $this->customSearchableColumns();
                       if ($result == "") {
                           $query = $this->getDefaultQuery($query, $searchable_array, $key, $search_value);
                        }else {
                          $query = $this->getDefaultQuery($query, $searchable_array, $key, $result);
                       }
                    }else {
                        $query = $this->getDefaultQuery($query, $searchableColumns, $key, $search_value);
                    }
                }elseif($keyType == 'string') {
                    $query->orWhere($this->getSqlColumn($key), 'like', '%'.$search_value.'%');
                } else if($keyType == 'date') {
                    if($dt = strtotime(str_replace('/', '-',$search_value))) {
                        $dt = date('Y-m-d', $dt);
                        $query->orWhereRaw("DATE(".$this->getSqlColumn($key).") = DATE('{$dt}')");
                    }
                } elseif ($keyType == 'integer') {
                    if (is_int(filter_var($search_value, FILTER_VALIDATE_INT))) {
                        $query->orWhere($this->getSqlColumn($key), '=', $search_value);
                    } else {
                        $query->orWhere($this->getSqlColumn($key), '=', '-1');
                    }
                    
                }else {
                    $query->orWhere($this->getSqlColumn($key), '=', $search_value);
                }
            }
        });
    }

    public function ajaxSearchColumns(&$queryBuilder)
    {
        $searchableColumns = $this->searchableColumns();
        $queryBuilder->where(function($query) use ($searchableColumns) {
            foreach(request()->get('filters', []) as $key => $val) {
                if(isset($searchableColumns[$key])) {
                    if(method_exists($this, "customSearchableColumns") && array_key_exists($key, $this->customSearchableColumns()) ) {
                        if(method_exists($this, "search".studly_case($key))) {
                           $result = call_user_func_array(array($this, "search".studly_case($key)), array($val));
                           $searchable_array = $this->customSearchableColumns();
                           if ($result == "") {
                              $query = $this->getIndividualDefaultQuery($query, $searchable_array, $key, $val);
                            }else {
                              $query = $this->getIndividualDefaultQuery($query, $searchable_array, $key, $result);
                           }
                        }else {
                            $query = $this->getIndividualDefaultQuery($query, $searchableColumns, $key, $val);
                        }
                    } elseif($searchableColumns[$key] == 'string') {
                        if (!empty($this->fuzzySearch)) {
                            $query->where($this->getSqlColumn($key), 'like', '%'.$val.'%');
                        }else {
                            $query->where($this->getSqlColumn($key), '=', $val);
                        }
                    } elseif($searchableColumns[$key] == 'date') {
                        if($dt = strtotime(str_replace('/', '-', $val))) {
                            $dt = date('Y-m-d', $dt);
                            $query->whereRaw("DATE(".$this->getSqlColumn($key).") = DATE('{$dt}')");
                        }
                    } elseif($searchableColumns[$key] == 'daterange') {
                        $range = explode(' - ', $val);
                        $fromDate = date('Y-m-d 00:00:00', strtotime($range[0]));
                        $toDate = date('Y-m-d 23:59:59', strtotime($range[1]));
                        $query->whereBetween($this->getSqlColumn($key), array($fromDate, $toDate));
                    } elseif ($searchableColumns[$key] == 'integer') {
                        if (is_int(filter_var($val, FILTER_VALIDATE_INT))) {
                            if (!empty($this->fuzzySearch)) {
                                $query->where($this->getSqlColumn($key), 'like', '%'.$val.'%');
                            }else {
                                $query->where($this->getSqlColumn($key), '=', $val);
                            }
                        } else {
                            $query->where($this->getSqlColumn($key), '=', '-1');
                        }
                        
                    } else {
                        $query->where($this->getSqlColumn($key), '=', $val);
                    }

                }
            }
        });
    }

    public function getDefaultQuery(&$query, $searchable_array, $key, $val)
    {
        if ($searchable_array[$key] == 'integer') {
            if (is_int(filter_var($val, FILTER_VALIDATE_INT))) {
                $query->orWhere($this->getSqlColumn($key), '=', $val);
            } else {
                $query->orWhere($this->getSqlColumn($key), '=', '-1');
            }
            
        } elseif($searchable_array[$key] == 'string') {
            if (!empty($this->fuzzySearch)) {
                $query->orWhere($this->getSqlColumn($key), 'like', '%'.$val.'%');
            }else {
                $query->orWhere($this->getSqlColumn($key), '=', $val);
            }
        } elseif($searchable_array[$key] == 'date') {
            if($dt = strtotime(str_replace('/', '-', $val))) {
                $dt = date('Y-m-d', $dt);
                $query->orWhereRaw("DATE(".$this->getSqlColumn($key).") = DATE('{$dt}')");
            }
        } elseif($searchable_array[$key] == 'daterange') {
            $range = explode(' - ', $val);
            $fromDate = date('Y-m-d 00:00:00', strtotime($range[0]));
            $toDate = date('Y-m-d 23:59:59', strtotime($range[1]));
            $query->orWhereBetween($this->getSqlColumn($key), array($fromDate, $toDate));
        }else {
            $query->orWhere($this->getSqlColumn($key), '=', $val);
        }

        return $query;
    }

    public function getIndividualDefaultQuery(&$query, $searchable_array, $key, $val)
    {
        if ($searchable_array[$key] == 'integer') {
            if (is_int(filter_var($val, FILTER_VALIDATE_INT))) {
                $query->where($this->getSqlColumn($key), '=', $val);
            } else {
                $query->where($this->getSqlColumn($key), '=', '-1');
            }
            
        } elseif($searchable_array[$key] == 'string') {
            if (!empty($this->fuzzySearch)) {
                $query->where($this->getSqlColumn($key), 'like', '%'.$val.'%');
            }else {
                $query->where($this->getSqlColumn($key), '=', $val);
            }
        } elseif($searchable_array[$key] == 'date') {
            if($dt = strtotime(str_replace('/', '-', $val))) {
                $dt = date('Y-m-d', $dt);
                $query->whereRaw("DATE(".$this->getSqlColumn($key).") = DATE('{$dt}')");
            }
        } elseif($searchable_array[$key] == 'daterange') {
            $range = explode(' - ', $val);
            $fromDate = date('Y-m-d 00:00:00', strtotime($range[0]));
            $toDate = date('Y-m-d 23:59:59', strtotime($range[1]));
            $query->whereBetween($this->getSqlColumn($key), array($fromDate, $toDate));
        }else {
            $query->where($this->getSqlColumn($key), '=', $val);
        }

        return $query;
    }


    /**
     * Get ColumnID to sql field name
     * @param $column
     * @return mixed
     */
    public function getSqlColumn($column) {
        if(method_exists($this, "mapDBColumns")) {
            $sqlMap = $this->mapDBColumns();
            if(isset($sqlMap[$column])) {
                return $sqlMap[$column];
            }
        }
        return $column;
    }


    /**
     * Process Each Result row
     * @param array $data
     * @return array
     */
    public function processRows($data = array())
    {
        $returnData = [];
        $columns = $this->columns();

        if( empty($columns) ) {
            return [];
        }

        if( !empty($data) ) {
            foreach($data as $row) {
                $row = (array) $row;
                $tmpArray = [];
                foreach($columns as $key => $title) {
                    if(method_exists($this, "getColumn".studly_case($key))) {
                        $val = call_user_func_array(array($this, "getColumn".studly_case($key)), array($row, request()->get('action')));
                    } else if( isset($row[$key]) ) {
                        $val = $row[$key];
                    } else {
                        $val = "";
                    }
                    $tmpArray[$key] = $val;
                }
                $returnData[] = $tmpArray;
            }
        }
        return $returnData;
    }


    /**
     * return Illuminate Database Query Builder
     * @return cursor
     */
    abstract public function resource();


    /**
     * Return Columns to Display on Table
     * @return array ['id' => 'title']
     */
    abstract public function columns();


    /**
     * Return columns id which you want to allow on sorting
     * @return array
     */
    public function sortableColumns()
    {
        return [];
    }


    /**
     * Return columns id which you want to allow on searching
     * @return array
     */
    public function searchableColumns()
    {
        return [];
    }


    /**
     * Return columns id with filter options
     * @return array
     */
    public function filters()
    {
        return ['id' => ['label' => 'ID', 'data' => ['key' => 'val']]];
    }


    /**
     * Return columns id with label which you want to allow on download
     * @return array
     */
    public function downloadableColumns()
    {
        return [];
    }
}
