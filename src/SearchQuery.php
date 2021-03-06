<?php

namespace Alexschwarz89\EasyMysqliFulltext;
use Aura\SqlQuery\QueryFactory;

/**
 * Class SearchQuery
 *
 * Builds a MYSQL-compatible query to be used with Search Class
 *
 * @author Alex Schwarz <alexschwarz@live.de>
 * @package Alexschwarz89\MysqliFulltext
 */
class SearchQuery
{
    /**
     * The table name, where to search at
     *
     * @var String
     */
    private $table          = null;
    /**
     * The columns where to search at (e.g. 'title, description')
     * @var null
     */
    private $searchFields   = null;
    /**
     * The columns to include in the results
     * @var array
     */
    private $selectFields   = array('*');
    /**
     * The conditions built with addCondition, just stored here
     * @var null
     */
    private $searchConditions    = null;
    /**
     * If set to 'relevance' will automatically select the relevance
     * and does everything for you.
     *
     * @var string
     */
    private $orderBy        = 'relevance';
    /**
     * Ascending or Descending Sort
     *
     * @var string
     */
    private $ascDesc        = 'DESC';

    /**
     * Instance of Alexschwarz89\MysqliFulltext\Search
     * @var Search
     */
    private $searchInstance = null;

    /**
     * The final composed query after all work is done
     *
     * @var
     */
    public $composedQuery;

    /**
     * Pass the instance of Search
     * This is needed for escaping with mysqli_escape_string
     *
     * @param Search $search
     */
    public function __construct(Search $search)
    {
        $this->searchInstance = $search;
    }

    /**
     * Sets the table name (as String) to search at
     *
     * @param $table
     * @return $this
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Sets the fields to search at (e.g. 'title, description')
     *
     * @param String $fields
     * @return $this
     */
    public function setSearchFields($fields)
    {
        $this->searchFields = $fields;
        return $this;
    }

    /**
     * Sets the fields to include in the search results
     * Must be an Array (['*'] to select all fields)
     *
     * @param Array $fields
     * @return $this
     */
    public function setSelectFields($fields)
    {
        $this->selectFields = $fields;
        return $this;
    }

    /**
     * A Term that must not be present in any of the rows that are returned
     *
     * @param $term
     * @return $this
     */
    public function exclude($term)
    {
        $this->addCondition('-', $term);
        return $this;
    }

    /**
     * This word must be present in each row that is returned
     *
     * @param $term
     * @return $this
     */
    public function mustInclude($term)
    {
        $this->addCondition('+', $term);
        return $this;
    }

    /**
     * This word is optional, but rows that contain it are rated higher.
     * This mimics the behavior of MATCH() ... AGAINST() without the IN BOOLEAN MODE modifier.
     *
     * @param $term
     * @return $this
     */
    public function canInclude($term)
    {
        $this->addCondition('', $term);
        return $this;
    }

    /**
     * A row containing a word marked with tilde is rated lower than others, but is NOT excluded,
     * as it would be with exclude function
     *
     * @param $term
     * @return $this
     */
    public function preferWithout($term)
    {
        $this->addCondition('~', $term);
        return $this;
    }

    /**
     * If you want to change the default behaviour to rank by relevance
     * you can specify an order by string here (e.g. 'title')
     *
     * @param $fields
     * @param string $ascDesc
     * @return $this
     */
    public function orderBy($fields, $ascDesc='DESC')
    {
        $this->orderBy = $fields;
        $this->ascDesc = $ascDesc;
        return $this;
    }

    /**
     * Adds a condition which will be composed later
     *
     * @param $operator
     * @param null $value
     */
    protected function addCondition($operator, $value=null) {
        $this->searchConditions[] = [
            'value' => $value,
            'operator' => $operator
        ];
    }


    /**
     * Returns all previously defined search Conditions as a String
     *
     * @return string
     */
    public function getSearchConditionsString()
    {
        $terms = '';

        foreach ($this->searchConditions as $condition) {
            $terms .= $condition['operator'] . $condition['value'] . ' ';
        }

        return $terms;
    }

    /**
     * Composes the actual query that later will be sent to the database
     * and returns it.
     *
     * @return string
     */
    public function compose()
    {
        $queryFactory = new QueryFactory('mysql');
        $select       = $queryFactory->newSelect();
        $terms        = $this->getSearchConditionsString();

        $matchString    = "MATCH (" . $this->searchInstance->db->real_escape_string($this->searchFields) . ")";
        $matchString   .= "AGAINST ('" . $this->searchInstance->db->real_escape_string($terms) . "' IN BOOLEAN MODE)";

        if ($this->orderBy == 'relevance') {
            $this->selectFields[] = $matchString . ' AS relevance';
        }

        $select->from($this->table)
            ->cols($this->selectFields)
            ->orderBy(array($this->orderBy . ' ' . $this->ascDesc));
        $select->where($matchString);

        return (string) $select;
    }

    /**
     * Returns the actual composed query as a string
     *
     * @return string
     */
    public function __toString()
    {
        return $this->compose();
    }
}