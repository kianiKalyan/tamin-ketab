<?php

class ModelGenerator extends CI_Controller
{
    function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->load->database();

        $propertyModels = "";
        $loadModels = "";

        //
        $tables = $this->db->list_tables();
        if($tables != null and count($tables) > 0)
        {
            foreach ($tables as $tableName)
            {
                $modelName = "";
                $tableExplode = explode("_", $tableName);
                foreach ($tableExplode as $item)
                {
                    $modelName .= ucfirst($item);
                }

                $propertyModels .= "* @property $modelName\t\t\t\$$modelName\n";
                $loadModels .= "\$this->load->model(\"$modelName\", \"\", true);\n";

                //
                if(!file_exists(APPPATH."/models/".$modelName.".php"))
                {
                    $tableCols = $this->db->list_fields($tableName);

                    //
                    $fileContent = "<?php\n\n";
                    $fileContent .= "defined('BASEPATH') OR exit('No direct script access allowed');\n\n";
                    $fileContent .= "class $modelName extends CI_Model\n";
                    $fileContent .= "{";

                    $fileContent .= "\n\tprivate \$tblName = \"$tableName\";\n";

                    //
                    if($tableCols != null and count($tableCols) > 0)
                    {
                        // params
                        $fileContent .= "\n\t// parameters";
                        foreach($tableCols as $tableCol)
                        {
                            $fileContent .= "\n\tprivate $$tableCol;";
                        }

                        // get
                        $fileContent .= "\n\n\t// get";
                        foreach($tableCols as $tableCol)
                        {
                            $getName = "";
                            $tableColExplode = explode("_", $tableCol);
                            foreach ($tableColExplode as $item)
                            {
                                $getName .= ucfirst($item);
                            }

                            $fileContent .= "\n\tpublic function get$getName()";
                            $fileContent .= "\n\t{";
                            $fileContent .= "\n\t\treturn \$this->$tableCol;";
                            $fileContent .= "\n\t}\n";
                        }

                        // set
                        $fileContent .= "\n\t// set";
                        foreach($tableCols as $tableCol)
                        {
                            $setName = "";
                            $tableColExplode = explode("_", $tableCol);
                            foreach ($tableColExplode as $item)
                            {
                                $setName .= ucfirst($item);
                            }

                            $fileContent .= "\n\tpublic function set$setName(\$value)";
                            $fileContent .= "\n\t{";
                            $fileContent .= "\n\t\t\$this->$tableCol = \$value;";
                            $fileContent .= "\n\t}\n";
                        }

                        // create object
                        $fileContent .= "\n\t// create object";

                        $fileContent .= "\n\tprivate function createObject(\$row)";
                        $fileContent .= "\n\t{";
                        $fileContent .= "\n\t\t\$object = null;\n";
                        $fileContent .= "\n\t\tif(\$row != null)";
                        $fileContent .= "\n\t\t{";
                        $fileContent .= "\n\t\t\t\$object = new $modelName();";
                        foreach($tableCols as $tableCol)
                        {
                            $setName = "";
                            $tableColExplode = explode("_", $tableCol);
                            foreach ($tableColExplode as $item)
                            {
                                $setName .= ucfirst($item);
                            }

                            $fileContent .= "\n\t\t\t\$object->set$setName(\$row->$tableCol);";
                        }
                        $fileContent .= "\n\t\t}\n";
                        $fileContent .= "\n\t\treturn \$object;";
                        $fileContent .= "\n\t}\n";
                        // create object array
                        $fileContent .= "\n\tprivate function createObjectArray(\$rows)";
                        $fileContent .= "\n\t{";
                        $fileContent .= "\n\t\t\$objectArray = array();\n";
                        $fileContent .= "\n\t\tif(\$rows != null and count(\$rows) > 0)";
                        $fileContent .= "\n\t\t{";
                        $fileContent .= "\n\t\t\t\$i = 0;\n";
                        $fileContent .= "\n\t\t\tforeach(\$rows as \$row)";
                        $fileContent .= "\n\t\t\t{";
                        $fileContent .= "\n\t\t\t\t\$objectArray[\$i] = \$this->createObject(\$row);";
                        $fileContent .= "\n\t\t\t\t\$i++;";
                        $fileContent .= "\n\t\t\t}";
                        $fileContent .= "\n\t\t}\n";
                        $fileContent .= "\n\t\treturn \$objectArray;";
                        $fileContent .= "\n\t}\n";

                        // save
                        $fileContent .= "\n\t// save";
                        $fileContent .= "\n\tpublic function save()";
                        $fileContent .= "\n\t{";
                        $fileContent .= "\n\t\t\$data = array";
                        $fileContent .= "\n\t\t(";
                        foreach($tableCols as $tableCol)
                        {
                            $fileContent .= "\n\t\t\t\"$tableCol\" => \$this->$tableCol,";
                        }
                        $fileContent .= "\n\t\t);\n";
                        $fileContent .= "\n\t\tif(!isset(\$this->id))";
                        $fileContent .= "\n\t\t{";
                        $fileContent .= "\n\t\t\t\$result = \$this->db->insert(\$this->tblName, \$data);";
                        $fileContent .= "\n\t\t\tif(\$result) \$this->setId(\$this->db->insert_id());";
                        $fileContent .= "\n\t\t}";
                        $fileContent .= "\n\t\telse";
                        $fileContent .= "\n\t\t{";
                        $fileContent .= "\n\t\t\t\$this->db->where(\"id\", \$this->id);";
                        $fileContent .= "\n\t\t\t\$result = \$this->db->update(\$this->tblName, \$data);";
                        $fileContent .= "\n\t\t}\n";
                        $fileContent .= "\n\t\treturn \$result;";
                        $fileContent .= "\n\t}\n";

                        // delete
                        $fileContent .= "\n\t// delete";
                        $fileContent .= "\n\tpublic function delete()";
                        $fileContent .= "\n\t{";
                        $fileContent .= "\n\t\t\$result = \$this->db->delete(\$this->tblName, \"id='\$this->id'\");\n";
                        $fileContent .= "\n\t\treturn \$result;";
                        $fileContent .= "\n\t}\n";

                        // findFirst
                        $fileContent .= "\n\t// findFirst";
                        $fileContent .= "\n\tpublic function findFirst(\$queryItems = null)";
                        $fileContent .= "\n\t{";
                        $fileContent .= "\n\t\t\$database = new Database();";
                        $fileContent .= "\n\t\t\$row = \$database->findFirst(\$this->tblName, \$queryItems);\n";
                        $fileContent .= "\n\t\treturn \$this->createObject(\$row);";
                        $fileContent .= "\n\t}\n";

                        // find
                        $fileContent .= "\n\t// find";
                        $fileContent .= "\n\t/**";
                        $fileContent .= "\n\t* @param null \$queryItems";
                        $fileContent .= "\n\t* @return ".$modelName."[]";
                        $fileContent .= "\n\t*/";
                        $fileContent .= "\n\tpublic function find(\$queryItems = null)";
                        $fileContent .= "\n\t{";
                        $fileContent .= "\n\t\t\$database = new Database();";
                        $fileContent .= "\n\t\t\$rows = \$database->find(\$this->tblName, \$queryItems);\n";
                        $fileContent .= "\n\t\treturn \$this->createObjectArray(\$rows);";
                        $fileContent .= "\n\t}\n";

                        // distinct
                        $fileContent .= "\n\t// distinct";
                        $fileContent .= "\n\t/**";
                        $fileContent .= "\n\t* @param null \$queryItems";
                        $fileContent .= "\n\t* @return ".$modelName."[]";
                        $fileContent .= "\n\t*/";
                        $fileContent .= "\n\tpublic function distinct(\$queryItems = null)";
                        $fileContent .= "\n\t{";
                        $fileContent .= "\n\t\t\$database = new Database();";
                        $fileContent .= "\n\t\t\$rows = \$database->distinct(\$this->tblName, \$queryItems);\n";
                        $fileContent .= "\n\t\treturn \$this->createObjectArray(\$rows);";
                        $fileContent .= "\n\t}\n";

                        // count
                        $fileContent .= "\n\t// count";
                        $fileContent .= "\n\tpublic function count(\$where = \"\")";
                        $fileContent .= "\n\t{";
                        $fileContent .= "\n\t\t\$database = new Database();";
                        $fileContent .= "\n\t\t\$count = \$database->count(\$this->tblName, \$where);\n";
                        $fileContent .= "\n\t\treturn \$count;";
                        $fileContent .= "\n\t}\n";

                        // max
                        $fileContent .= "\n\t// max";
                        $fileContent .= "\n\tpublic function max(\$queryItems = null)";
                        $fileContent .= "\n\t{";
                        $fileContent .= "\n\t\t\$database = new Database();";
                        $fileContent .= "\n\t\t\$max = \$database->max(\$this->tblName, \$queryItems);\n";
                        $fileContent .= "\n\t\treturn \$max;";
                        $fileContent .= "\n\t}\n";

                        // min
                        $fileContent .= "\n\t// min";
                        $fileContent .= "\n\tpublic function min(\$queryItems = null)";
                        $fileContent .= "\n\t{";
                        $fileContent .= "\n\t\t\$database = new Database();";
                        $fileContent .= "\n\t\t\$min = \$database->min(\$this->tblName, \$queryItems);\n";
                        $fileContent .= "\n\t\treturn \$min;";
                        $fileContent .= "\n\t}\n";

                        // sum
                        $fileContent .= "\n\t// sum";
                        $fileContent .= "\n\tpublic function sum(\$queryItems = null)";
                        $fileContent .= "\n\t{";
                        $fileContent .= "\n\t\t\$database = new Database();";
                        $fileContent .= "\n\t\t\$sum = \$database->sum(\$this->tblName, \$queryItems);\n";
                        $fileContent .= "\n\t\treturn \$sum;";
                        $fileContent .= "\n\t}";
                    }

                    //
                    $fileContent .= "\n}";

                    //
                    file_put_contents(APPPATH."/models/".$modelName.".php", $fileContent);
                }
            }
        }

        //
        echo $propertyModels."\n\n\n\n".$loadModels;

        exit;
    }
}

?>