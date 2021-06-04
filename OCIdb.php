<?php

class OCIdb
{
    private $MAXIMUM_PREFETCH_NUMBER = 2000; //maximum of rows to be fetched by fetchTable
    private $pAutoCommit = OCI_COMMIT_ON_SUCCESS; //OCI_NO_AUTO_COMMIT or OCI_COMMIT_ON_SUCCESS;
    private $_data_format = 'dd.mm.yyyy hh24:mi';
    private $_user='john';
    private $_password='mysecret';
    private $idOra='10.205.32.12:1523/SID';

    protected $ociObject;
    protected $_table_name = '';
    public $_idrowid = '';
    public $arrColsInfo = [];
    public $_error_code = 0;
    public $_error_message = '';
    public $_nr_rows;
    public $_nr_columns;
    public $thereAreMoreRows = false;
    private $_conn = false; //connection to the database
    public $connDB = false; //exposed connection to the database
    private $initialRowValue = [];
    private $_res_parse;
    private $_res_res;
    private $skipedAttributes = [];
    private $skipedAttributesOnCreate = [];
    private $skipedAttributesOnUpdate = [];
    private $stId;
    private $rowExists = false;
    private $columnsAlias = [];
    public function __construct()
    {
        if (!$this->_conn) {
            $this->sql_connect();
        }
    }
    public function sql_connect($user = null, $password = null, $idOra = null)
    {
        if (0 == func_num_args()) {
            $this->_conn = oci_pconnect($this->_user, $this->_password, $this->idOra, 'AL32UTF8');
        } else {
            $this->_conn = oci_pconnect($user, $password, $idOra, 'AL32UTF8');
        }
        if (!$this->_conn) {
            $m = oci_error();
            $this->_error_code = $m['code'];
            $this->_error_message = $m['message'];
            return false;
        } else {
            $this->connDB=$this->_conn;
            return $this->_conn;
        }
    }
    public function loadModel($pmodel)
    {
        $this->ociObject = new $pmodel();
        if (method_exists($this->ociObject, 'initialize')) {
            $this->ociObject->initialize();
        }
        $this->ociObject->setSource();
        $this->ociObject->setAllColumnsAndDatatype();
        return $this->ociObject;
    }
    public function sql_fetch_row2_into()
    {
        if ($this->_res_res = oci_fetch_array($this->_res_parse, OCI_BOTH + OCI_RETURN_NULLS)) {
            return true;
        } else {
            return false;
        }
    }
    public function commit()
    {
        oci_commit($this->_conn);
    }
    public function rollback($psavepointname = null)
    {
        if (isset($psavepointname) && strlen($psavepointname) > 0) {
            $stid = oci_parse($this->_conn, 'ROLLBACK TO ' . $psavepointname);
            oci_execute($stid, OCI_NO_AUTO_COMMIT);
        } else {
            oci_rollback($this->_conn);
        }
    }
    public function beginTransaction($psavepointname)
    {
        if (isset($psavepointname) && strlen($psavepointname) > 0) {
            $this->pAutoCommit = OCI_NO_AUTO_COMMIT;
            $stid = oci_parse($this->_conn, 'SAVEPOINT ' . $psavepointname);
            return (oci_execute($stid, OCI_NO_AUTO_COMMIT));
        } else {
            return false;
        }
    }
    public function reset()
    {
        foreach ($this->arrColsInfo as $x_column => $x_datatype) {
            if ($x_column != '_idrowid') {
                $this->{$x_column} = '';
            }
        }
    }
    public function findFirst($p_array_of_params = null)
    {
        $p_where_conditions = "";
        $bindingTable = [];
        $this->rowExists = false;
        $orderByClause = '';
        $sqlForColumns = '';
        if (func_num_args() > 0) {
            if (array_key_exists('conditions', $p_array_of_params)) {
                $p_where_conditions = $p_array_of_params["conditions"];
            }
            if (array_key_exists('bind', $p_array_of_params)) {
                $bindingTable = $p_array_of_params["bind"];
            }
            if (array_key_exists('order by', $p_array_of_params)) {
                $orderByClause = " order by " . $p_array_of_params["order by"];
            }
        }
        $firstCycle = true;
        foreach ($this->arrColsInfo as $x_column => $x_datatype) {
            if ($x_datatype == 'DATE') {
                $x_column_name_for_selection = "to_char(x.$x_column,'$this->_data_format')";
            } else {
                $x_column_name_for_selection = "x.$x_column";
            }
            $sqlForColumns .= ($firstCycle ? '' : ',') . $x_column_name_for_selection . ' as "' . $x_column . '"';
            $firstCycle = false;
        }
        $cmd_sql = 'select * from (select ' . $sqlForColumns . ',rowidtochar(rowid) as "_idrowid" from ' . $this->_table_name . ' x WHERE ' . $p_where_conditions . $orderByClause . ')  where rownum=1';
        $this->stId = oci_parse($this->_conn, $cmd_sql);
        foreach ($bindingTable as $bindingName => &$bindingValue) {
            oci_bind_by_name($this->stId, $bindingName, $bindingValue);
        }
        if (!$this->stId) {
            $e = oci_error($cmd_sql);
            $this->_error_message = $e["message"];
            $this->_error_code = $e["code"];
        }
        if (oci_execute($this->stId)) {
            $this->_nr_columns = oci_num_fields($this->stId);
            if (($row = oci_fetch_object($this->stId)) != false) {
                $this->rowExists = true;
                foreach ($row as $var => $value) {
                    $this->{strtolower($var)} = $value;
                    $this->initialRowValue[strtolower($var)] = $value;
                }
            }
        } else {
            echo "error";
            $e = oci_error($this->stId);
            $this->_error_message = $e["message"];
            $this->_error_code = $e["code"];
            return false;
        }
        return $this->rowExists;
    }
    public function find($p_array_of_params = null)
    {
        $p_where_conditions = "1=1";
        $bindingTable = [];
        $this->rowExists = false;
        $orderByClause = '';
        $sqlForColumns = '';
        if (func_num_args() > 0) {
            if (array_key_exists('conditions', $p_array_of_params)) {
                $p_where_conditions = $p_array_of_params["conditions"];
            }
            if (array_key_exists('bind', $p_array_of_params)) {
                $bindingTable = $p_array_of_params["bind"];
            }
            if (array_key_exists('order by', $p_array_of_params)) {
                $orderByClause = " order by " . $p_array_of_params["order by"];
            }
        }
        $firstCycle = true;
        foreach ($this->arrColsInfo as $x_column => $x_datatype) {
            if ($x_datatype == 'DATE') {
                $x_column_name_for_selection = "to_char(x.$x_column,'$this->_data_format')";
            } else {
                $x_column_name_for_selection = "x.$x_column";
            }
            $sqlForColumns .= ($firstCycle ? '' : ',') . $x_column_name_for_selection . ' as "' . $x_column . '"';
            $firstCycle = false;
        }
        $cmd_sql = 'select ' . $sqlForColumns . ',rowidtochar(rowid) as "_idrowid" from ' . $this->_table_name . ' x WHERE ' . $p_where_conditions . $orderByClause;
        $this->stId = oci_parse($this->_conn, $cmd_sql);
        foreach ($bindingTable as $bindingName => &$bindingValue) {
            oci_bind_by_name($this->stId, $bindingName, $bindingValue);
        }
        if (!$this->stId) {
            $e = oci_error($cmd_sql);
            $this->_error_message = $e["message"];
            $this->_error_code = $e["code"];
        }
        if (oci_execute($this->stId)) {
            $this->_nr_columns = oci_num_fields($this->stId);
            if (($row = oci_fetch_object($this->stId)) != false) {
                $this->rowExists = true;
                foreach ($row as $var => $value) {
                    $this->{strtolower($var)} = $value;
                    $this->initialRowValue[strtolower($var)] = $value;
                }
            } else {
                $this->rowExists = false;
            }
            //$v_result=oci_fetch_object($res_parse,OCI_FETCHSTATEMENT_BY_ROW+OCI_ASSOC+OCI_RETURN_NULLS);
        } else {
            $e = oci_error($this->stId);
            $this->_error_message = $e["message"];
            $this->_error_code = $e["code"];
            return false;
        }
        return $this->rowExists;
    }
        public function paginate($p_array_of_params = null)
    {
            function delete_col(&$array, $key) {
                return array_walk($array, function (&$v) use ($key) {
                    unset($v[$key]);
                });
            }
        $responce = new stdClass();
        $responce->nrOfRowsInPage=0;
        $responce->pageNumber=0;
        $responce->totalRows=0;
        $responce->totalPages=0;
        $responce->rows=[];

        $p_where_conditions = "1=1";
        $bindingTable = [];
        $this->rowExists = false;
        $orderByClause = '';
        $result = [];
        $sqlForColumns = '';
        if (func_num_args() > 0) {
            if (array_key_exists('conditions', $p_array_of_params)) {
                $p_where_conditions = $p_array_of_params["conditions"];
            }
            if (array_key_exists('bind', $p_array_of_params)) {
                $bindingTable = $p_array_of_params["bind"];
            }
            if (array_key_exists('order by', $p_array_of_params)) {
                $orderByClause = " order by " . $p_array_of_params["order by"];
            }
            if (array_key_exists('paginator', $p_array_of_params)) {
                $paginator = $p_array_of_params["paginator"];
                $nrOfRowsInPage=intval($paginator['nrOfRowsInPage']);
                $pageNumber=intval($paginator['pageNumber']);
                $responce->nrOfRowsInPage=$nrOfRowsInPage;
                $responce->pageNumber=$pageNumber;
            }
        }
        $firstCycle = true;
        foreach ($this->arrColsInfo as $x_column => $x_datatype) {
            if (array_key_exists($x_column, $this->columnsAlias)) {
                $sqlForColumns .= ($firstCycle ? '' : ',') . ' x.' . $x_column . ' as "' . $this->columnsAlias[$x_column] . '"';
            } else {
                $sqlForColumns .= ($firstCycle ? '' : ',') . ' x.' . $x_column . ' as "' . $x_column . '"';
            }
            $firstCycle = false;
        }
        $cmd_sql = 'select * from (select x.*,rownum as "rowNumber" from (select ' . $sqlForColumns . ',rowidtochar(rowid) as "_idrowid",COUNT(*) OVER () RESULT_COUNT from ' . $this->_table_name . ' x WHERE ' . $p_where_conditions . $orderByClause.') x
        WHERE rownum < (('.$pageNumber.' * '.$nrOfRowsInPage.') + 1 )
        )
        WHERE "rowNumber" >= ((('.$pageNumber.'-1) * '.$nrOfRowsInPage.') + 1)
        ';
      //die(var_dump($cmd_sql));
        $this->_res_parse = oci_parse($this->_conn, $cmd_sql);
        foreach ($bindingTable as $bindingName => &$bindingValue) {
            oci_bind_by_name($this->_res_parse, $bindingName, $bindingValue);
        }
        if (!$this->_res_parse) {
            $e = oci_error($cmd_sql);
            $this->_error_message = $e["message"];
            $this->_error_code = $e["code"];
        }
        if (oci_execute($this->_res_parse)) {
            $this->_nr_columns = oci_num_fields($this->_res_parse);
            $result = [];
            $this->_nr_rows = oci_fetch_all($this->_res_parse, $result, 0, $this->MAXIMUM_PREFETCH_NUMBER, OCI_FETCHSTATEMENT_BY_ROW + OCI_ASSOC + OCI_RETURN_NULLS);
            if (count($result) == 0) {
                $result = false;
            }
            else{
                $responce->totalRows=$result[0]['RESULT_COUNT'];
                $responce->totalPages=ceil($responce->totalRows/$responce->nrOfRowsInPage);
                delete_col($result,'RESULT_COUNT');
                $responce->rows=$result;
                die(json_encode($responce));
            }
        } else {
            echo "error";
            $e = oci_error($this->_res_parse);
            $this->_error_message = $e["message"];
            $this->_error_code = $e["code"];
            return false;
        }
        return $result;
    }
    public function fetchTable($p_array_of_params = null)
    {
        $p_where_conditions = "1=1";
        $bindingTable = [];
        $this->rowExists = false;
        $orderByClause = '';
        $result = [];
        $sqlForColumns = '';
        if (func_num_args() > 0) {
            if (array_key_exists('conditions', $p_array_of_params)) {
                $p_where_conditions = $p_array_of_params["conditions"];
            }
            if (array_key_exists('bind', $p_array_of_params)) {
                $bindingTable = $p_array_of_params["bind"];
            }
            if (array_key_exists('order by', $p_array_of_params)) {
                $orderByClause = " order by " . $p_array_of_params["order by"];
            }
        }
        $firstCycle = true;
        foreach ($this->arrColsInfo as $x_column => $x_datatype) {
            if (array_key_exists($x_column, $this->columnsAlias)) {
                $sqlForColumns .= ($firstCycle ? '' : ',') . ' x.' . $x_column . ' as "' . $this->columnsAlias[$x_column] . '"';
            } else {
                $sqlForColumns .= ($firstCycle ? '' : ',') . ' x.' . $x_column . ' as "' . $x_column . '"';
            }
            $firstCycle = false;
        }
        $cmd_sql = 'select ' . $sqlForColumns . ',rowidtochar(rowid) as "_idrowid" from ' . $this->_table_name . ' x WHERE ' . $p_where_conditions . $orderByClause;
        //die(var_dump($cmd_sql));
        $this->_res_parse = oci_parse($this->_conn, $cmd_sql);
        foreach ($bindingTable as $bindingName => &$bindingValue) {
            oci_bind_by_name($this->_res_parse, $bindingName, $bindingValue);
        }
        if (!$this->_res_parse) {
            $e = oci_error($cmd_sql);
            $this->_error_message = $e["message"];
            $this->_error_code = $e["code"];
        }
        if (oci_execute($this->_res_parse)) {
            $this->_nr_columns = oci_num_fields($this->_res_parse);
            $result = [];
            $this->_nr_rows = oci_fetch_all($this->_res_parse, $result, 0, $this->MAXIMUM_PREFETCH_NUMBER, OCI_FETCHSTATEMENT_BY_ROW + OCI_ASSOC + OCI_RETURN_NULLS);
            if (count($result) == 0) {
                $result = false;
            }
        } else {
            echo "error";
            $e = oci_error($this->_res_parse);
            $this->_error_message = $e["message"];
            $this->_error_code = $e["code"];
            return false;
        }
        return $result;
    }
    public function count()
    {
        return $this->_nr_rows;
    }
    public function next()
    {
        $this->rowExists = false;
        if (($row = oci_fetch_object($this->stId)) != false) {
            $this->rowExists = true;
            foreach ($row as $var => $value) {
                $this->{strtolower($var)} = $value;
                $this->initialRowValue[strtolower($var)] = $value;
            }
        }
        return $this->rowExists;
    }
    public function update($pcommittype = null)
    {
        $v_row_has_been_updated = false;
        if (func_num_args() > 0) {
            if ($pcommittype == 'commit') {
                $vAutoCommit = OCI_COMMIT_ON_SUCCESS;
            }
            if ($pcommittype == 'nocommit') {
                $vAutoCommit = OCI_NO_AUTO_COMMIT;
            }
        } else {
            $vAutoCommit = $this->pAutoCommit;
        }
        $cmd_sql = "update " . $this->_table_name . " set ";
        $first_column = true;
        $v_clob_returning = '';
        $nr_crt = 0;
        foreach (array_filter($this->arrColsInfo, function ($column) {
            return !in_array($column, $this->skipedAttributesOnUpdate);
        }, ARRAY_FILTER_USE_KEY) as $x_column => $x_datatype) {
            $v_sql_for_column = '';
            if ($this->initialRowValue[$x_column] != $this->{$x_column}) { //there is a change in column value
                $nr_crt++;
                switch (strtoupper($x_datatype)) {
                    case 'X':
                        $v_sql_for_column = "";
                        break;
                    case 'VARCHAR2':
                        $v_sql_for_column = $x_column . "=:v_" . $nr_crt;
                        break;
                    case 'CLOB':
                        $v_sql_for_column = $x_column . "=:v_" . $nr_crt;
                        break;
                    case 'DATE':
                        if (strtolower($this->{$x_column}) == 'sysdate') {
                            $v_sql_for_column = $x_column . "=sysdate";
                        } else {
                            $v_sql_for_column = $x_column . "=to_date(:v_" . $nr_crt . ",'" . $this->_data_format . "')";
                        }
                        break;
                    case 'NUMBER':
                        $v_sql_for_column = $x_column . "=:v_" . $nr_crt;
                        break;
                    default:
                        $v_sql_for_column = "";
                }
                $cmd_sql = $cmd_sql . (($first_column) ? "" : " , ") . $v_sql_for_column;
                $first_column = false;
            }
        }
        $cmd_sql .= " where rowid=chartorowid(:v_rowid)";
        //if(strtolower($_SERVER['LOGON_USER'])=='bcrwan\ioan.codreanu'){die(var_dump($cmd_sql));}

        if ($nr_crt == 0) { //there's nothing to be updated, abort
            return true;
        }
        $res_parse = oci_parse($this->_conn, $cmd_sql);
        if ($res_parse) {
            $nr_crt = 0;
        foreach (array_filter($this->arrColsInfo, function ($column) {
            return !in_array($column, $this->skipedAttributesOnUpdate);
        }, ARRAY_FILTER_USE_KEY) as $x_column => $x_datatype) { //there is a change in column value
        if ($this->initialRowValue[$x_column] != $this->{$x_column}) { //there is a change in column value
                     $nr_crt++;
                     if($x_datatype =='DATE' && (strtolower($this->{$x_column}) == 'sysdate')){
                         null;
                     }else{
                        oci_bind_by_name($res_parse, ":v_" . $nr_crt, $this->{$x_column});
                     }
                    }
                }
            oci_bind_by_name($res_parse, ":v_rowid", $this->_idrowid);
        } else { //error on parsing
            $e = oci_error($res_parse);
            $this->_error_message = $e["message"];
            $this->_error_code = $e["code"];
        }
        if (@oci_execute($res_parse, $vAutoCommit)) {
            $v_row_has_been_updated = true;
        } else {
            $e = oci_error($res_parse);
            $this->_error_message = $e["message"];
            $this->_error_code = $e["code"];
        }
        return $v_row_has_been_updated;
    }
    public function create($pcommittype = null)
    {
        $v_row_has_been_inserted = false;
        if (func_num_args() > 0) {
            if ($pcommittype == 'commit') {
                $vAutoCommit = OCI_COMMIT_ON_SUCCESS;
            }
            if ($pcommittype == 'nocommit') {
                $vAutoCommit = OCI_NO_AUTO_COMMIT;
            }
        } else {
            $vAutoCommit = $this->pAutoCommit;
        }
        $cmd_sql = "insert into " . $this->_table_name . " (";
        $first_column = true;
        foreach (array_filter($this->arrColsInfo, function ($column) {
            return !in_array($column, $this->skipedAttributesOnCreate);
        }, ARRAY_FILTER_USE_KEY) as $x_column => $x_datatype) {
            $cmd_sql = $cmd_sql . (($first_column) ? "" : ",") . $x_column;
            $first_column = false;
        }
        $cmd_sql = $cmd_sql . ") values (";
        $nr_crt = 0;
        $first_column = true;
        foreach (array_filter($this->arrColsInfo, function ($column) {
            return !in_array($column, $this->skipedAttributesOnCreate);
        }, ARRAY_FILTER_USE_KEY) as $x_column => $x_datatype) {
            $v_sql_for_column = '';
            $nr_crt++;
            switch (strtoupper($x_datatype)) {
                case 'X':
                    $v_sql_for_column = "";
                    break;
                case 'VARCHAR2':
                    $v_sql_for_column = ":v_" . $nr_crt;
                    break;
                case 'CLOB':
                    $v_sql_for_column = ":v_" . $nr_crt;
                    break;
                case 'DATE':
                    if (strtolower($this->{$x_column}) == 'sysdate') {
                        $v_sql_for_column = "sysdate";
                    } else {
                        $v_sql_for_column = "to_date(:v_" . $nr_crt . ",'" . $this->_data_format . "')";
                    }
                    break;
                case 'NUMBER':
                    $v_sql_for_column = ":v_" . $nr_crt;
                    break;
                default:
                    $v_sql_for_column = "";
            }
            $cmd_sql = $cmd_sql . (($first_column) ? "" : " , ") . $v_sql_for_column;
            $first_column = false;
        }
        $cmd_sql = $cmd_sql . ")";
        //die(var_dump($cmd_sql));
        if ($nr_crt == 0) { //there's nothing to be updated, abort
            return true;
        }
        $res_parse = oci_parse($this->_conn, $cmd_sql);
        if ($res_parse) {
            $nr_crt = 0;
            foreach (array_filter($this->arrColsInfo, function ($column) {
                return !in_array($column, $this->skipedAttributesOnCreate);
            }, ARRAY_FILTER_USE_KEY) as $x_column => $x_datatype) {
                $nr_crt++;
                if (strtoupper($x_datatype) == 'DATE' && strtolower($this->{$x_column}) == 'sysdate') {
                    null;
                } else {
                    oci_bind_by_name($res_parse, ":v_" . $nr_crt, $this->{$x_column});
                }
            }
        } else { //error on parsing
            die('error on parsing');
            $e = oci_error($res_parse);
            $this->_error_message = $e["message"];
            $this->_error_code = $e["code"];
        }
        if (@oci_execute($res_parse, $vAutoCommit)) {
            $v_row_has_been_inserted = true;
        } else {
            $e = oci_error($res_parse);
            $this->_error_message = $e["message"];
            $this->_error_code = $e["code"];
        }
        return $v_row_has_been_inserted;
    }
    public function delete($pcommittype = null)
    {
        $v_row_has_been_deleted = false;
        if (func_num_args() > 0) {
            if ($pcommittype == 'commit') {
                $vAutoCommit = OCI_COMMIT_ON_SUCCESS;
            }
            if ($pcommittype == 'nocommit') {
                $vAutoCommit = OCI_NO_AUTO_COMMIT;
            }
        } else {
            $vAutoCommit = $this->pAutoCommit;
        }
        $cmd_sql = "delete from " . $this->_table_name . " where  rowid=chartorowid(:v_rowid)";
        $res_parse = oci_parse($this->_conn, $cmd_sql);
        if ($res_parse) {
            oci_bind_by_name($res_parse, ":v_rowid", $this->_idrowid);
        } else { //error on parsing
            $e = oci_error($res_parse);
            $this->_error_message = $e["message"];
            $this->_error_code = $e["code"];
        }
        if (oci_execute($res_parse, $vAutoCommit)) {
            $v_row_has_been_deleted = true;
        } else {
            $e = oci_error($res_parse);
            $this->_error_message = $e["message"];
            $this->_error_code = $e["code"];
        }
        return $v_row_has_been_deleted;
    }
    public function exportAsArray()
    {
        $tmpArr = [];
        while ($this->rowExists) {
            $oneRow = [];
            foreach ($this->arrColsInfo as $x_column => $x_datatype) {
                if (array_key_exists($x_column, $this->columnsAlias)) {
                    $oneRow[$this->columnsAlias[$x_column]] = $this->{$x_column};
                } else {
                    $oneRow[$x_column] = $this->{$x_column};
                }
            }
            array_push($tmpArr, $oneRow);
            $this->next();
        }
        $this->_nr_rows=count($tmpArr);
        return $tmpArr;
    }
    public function sql_query($p_array_of_params = null)
    {
        $p_binding = null;
        $p_sql = null;
        $bindingTable = null;
        $p_commit = "no";
        $p_first_row_only = "no";
        $this->statement_type = "";
        $p_format = null;
        $vAutoCommit = OCI_NO_AUTO_COMMIT;
        if (func_num_args() > 0) { //exista parametri de rulare
            if (array_key_exists('sql', $p_array_of_params)) {
                $p_sql = $p_array_of_params["sql"];
            }
            if (array_key_exists('binding', $p_array_of_params)) {
                $bindingTable = $p_array_of_params["binding"];
            }
            if (array_key_exists('first_row_only', $p_array_of_params)) {
                $p_first_row_only = strtolower($p_array_of_params["first_row_only"]);
            } else {
                $p_first_row_only = "no";
            }
            if (array_key_exists('commit', $p_array_of_params)) {
                $p_commit = strtolower($p_array_of_params["commit"]);
            } else {
                $p_commit = "no";
            }
            if (array_key_exists('datatype', $p_array_of_params)) {
                $p_format = $p_array_of_params["datatype"];
            }
            if ($p_commit == 'yes') {
                $vAutoCommit = OCI_COMMIT_ON_SUCCESS;
            }
        }
        $this->_error_code = 0;
        if ($bindingTable) { //avem cod sql cu variabile binding
            $this->_res_parse = oci_parse($this->_conn, $p_sql);
            if ($this->_res_parse) {
                $this->statement_type = oci_statement_type($this->_res_parse);
                foreach ($bindingTable as $binding_variable_name => $binding_value) {
                    oci_bind_by_name($this->_res_parse, $binding_variable_name, $bindingTable[$binding_variable_name]);
                    //echo "try binding ".$binding_variable_name." to ".$bindingTable[$binding_variable_name];
                }
                if (oci_execute($this->_res_parse, $vAutoCommit)) {
                    if ($this->statement_type == "SELECT") {
                        if ($p_first_row_only == 'yes') {
                            $this->_res_res = oci_fetch_array($this->_res_parse, OCI_BOTH + OCI_RETURN_NULLS);
                            if ($p_format) {
                                switch (strtolower($p_format)) {
                                    case 'json':
                                        $this->_res_res = json_encode($this->_res_res);
                                        break;
                                    default:
                                        $this->_res_res = $this->_res_res;
                                }
                            }
                            return $this->_res_res;
                        } else {
                            $this->_nr_rows = oci_fetch_all($this->_res_parse, $this->_res_res, 0, -1, OCI_FETCHSTATEMENT_BY_ROW + OCI_ASSOC + OCI_RETURN_NULLS);
                            if ($p_format) {
                                switch (strtolower($p_format)) {
                                    case 'json':
                                        $this->_res_res = json_encode($this->_res_res);
                                        break;
                                    default:
                                        $this->_res_res = $this->_res_res;
                                }
                            }
                            return $this->_res_res;
                        }
                        oci_free_statement($this->_res_parse);
                        return $this->_res_res;
                    } else { //inseamna ca e un delete, update, sau insert
                        $this->_nr_rows = oci_num_rows($this->_res_parse);
                        oci_free_statement($this->_res_parse);
                        return $this->_nr_rows;
                    }
                } else {
                    $e = oci_error($this->_res_parse);
                    $this->_error_message = $e["message"];
                    $this->_error_code = $e["code"];
                    return false;
                }
            } else {
                $e = oci_error($this->_res_parse);
                $this->_error_message = $e["message"];
                $this->_error_code = $e["code"];
                return false;
            }
        } else { //e un cod fara binding
            if ($p_sql) {
                $this->cmd_sql = $p_sql;
            }
            $this->_res_parse = oci_parse($this->_conn, $this->cmd_sql);
            $this->statement_type = oci_statement_type($this->_res_parse);
            if (!$this->_res_parse) {
                $e = oci_error($this->cmd_sql);
                $this->_error_message = $e["message"];
                $this->_error_code = $e["code"];
                return false;
            } else { //echo $this->cmd_sql;
                if (oci_execute($this->_res_parse, $vAutoCommit)) {
                    $this->_error_code = 0;
                    $this->_error_message = "succes la oci_execute din sql_query";
                    $this->_nr_rows = oci_num_rows($this->_res_parse);
                    $this->_nr_columns = oci_num_fields($this->_res_parse);
                    if ($p_sql) { //fac si returnul, nu va mai face el fetch
                        if ($this->statement_type == "SELECT") {
                            if ($p_first_row_only == 'yes') {
                                $this->_res_res = oci_fetch_array($this->_res_parse, OCI_BOTH + OCI_RETURN_NULLS);
                                if ($p_format) {
                                    switch (strtolower($p_format)) {
                                        case 'json':
                                            $this->_res_res = json_encode($this->_res_res);
                                            break;
                                        default:
                                            $this->_res_res = $this->_res_res;
                                    }
                                }
                            } else {
                                $this->_nr_rows = oci_fetch_all($this->_res_parse, $this->_res_res, 0, -1, OCI_FETCHSTATEMENT_BY_ROW + OCI_ASSOC + OCI_RETURN_NULLS);
                                if ($p_format) {
                                    switch (strtolower($p_format)) {
                                        case 'json':
                                            $this->_res_res = json_encode($this->_res_res);
                                            break;
                                        default:
                                            $this->_res_res = $this->_res_res;
                                    }
                                }
                            }
                            oci_free_statement($this->_res_parse);
                            return $this->_res_res;
                        } else { //inseamna ca e un delete, update, sau insert
                            $this->_nr_rows = oci_num_rows($this->_res_parse);
                            oci_free_statement($this->_res_parse);
                            return $this->_nr_rows;
                        }
                    }
                } else {
                    $e = oci_error($this->_res_parse);
                    $this->_error_message = $e["message"];
                    $this->_error_code = $e["code"];
                }
                return false;
            }
        }
    }
    public function checkIfIsNull(String $pcolumnName)
    {
        return oci_field_is_null($this->stId, $pcolumnName);
    }
    protected function setAllColumnsAndDatatype()
    {
        $this->_error_code = 0;
        $v_result = false;
        $v_table_name = strtoupper($this->_table_name);
        $column_datatype = '';
        $sql_column_datatype_res_parse = oci_parse($this->_conn, "select COLUMN_NAME
                                                   ,CASE WHEN DATA_TYPE LIKE 'TIMESTAMP%' THEN 'DATE' ELSE DATA_TYPE end as DATA_TYPE
                                                   --,NULLABLE
     from all_tab_columns where table_name = :v_table_name");
        oci_bind_by_name($sql_column_datatype_res_parse, ":v_table_name", $v_table_name);
        if (oci_execute($sql_column_datatype_res_parse)) {
            if (oci_fetch_all($sql_column_datatype_res_parse, $v_result, 0, -1, OCI_FETCHSTATEMENT_BY_ROW + OCI_ASSOC + OCI_RETURN_NULLS)) {
                foreach ($v_result as $row) {
                    if (!in_array(strtolower($row['COLUMN_NAME']), $this->skipedAttributes)) {
                        $this->{strtolower($row['COLUMN_NAME'])} = '';
                        $this->initialRowValue[strtolower($row['COLUMN_NAME'])] = '';
                        $this->arrColsInfo[strtolower($row['COLUMN_NAME'])] = $row['DATA_TYPE'];
                    }
                }
                $v_result = true;
            }
        } else {
            $this->_error_code = 203;
            $this->_error_message = "error trying to set Columns and Datatype";
            $v_result = false;
        }
        return $v_result;
    }
    public function getColumnsInfo()
    {
        return $this->arrColsInfo;
    }
    public function setDataFormat($p_array_of_params = null)
    {
        if (func_num_args() > 0) {
            $this->_data_format = $p_array_of_params;
        }
    }
    protected function skipAttributes($p_array_of_params = null)
    {
        if (func_num_args() > 0) {
            $this->skipedAttributes = $p_array_of_params;
        }
    }
    protected function skipAttributesOnCreate($p_array_of_params = null)
    {
        if (func_num_args() > 0) {
            $this->skipedAttributesOnCreate = $p_array_of_params;
        }
    }
    protected function skipAttributesOnUpdate($p_array_of_params = null)
    {
        if (func_num_args() > 0) {
            $this->skipedAttributesOnUpdate = $p_array_of_params;
        }
    }
    protected function setColumnAlias($p_array_of_params = null)
    {
        if (func_num_args() > 0) {
            $this->columnsAlias = $p_array_of_params;
        }
    }
}
