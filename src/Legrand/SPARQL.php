<?php

namespace Legrand;

/**
*
* This class help to get content by using a SPARQL endpoint
*
* @author Damien Legrand  < http://damienlegrand.com >
*/

class SPARQL {
	
	//The endpoint base URL without parameters
	public $baseUrl			= "http://dbpedia.org/sparql";

	//The result's type
	public $format 			= "json";

	//The GET or POST parameter name for the query
	public $queryParam 		= "query";

	//The GET or POST parameter name for the result's format
	public $formatParam 		= "format";

	//GET or POST
	public $method 			= "GET";

	//The SPARQL request
	public $sparql 			= "";

	//Arrays to build the request
	public $prefixes 		= array();
	public $distinctSelect 		= false;
	public $variables 		= array();
	public $wheres 			= array();
	public $orders			= array();
	public $limitNb			= null;
	public $offsetNb		= null;
	public $insertGraph		= null;
	public $deleteGraph		= null;
	public $deleteCond		= null;
	public $unions 			= array(); //array of SPARQL object
	
	/**
	*
	* METHODS
	*
	**/

	public function prefixe($ns, $x)
	{ 
		$this->prefixes[] = "PREFIX $ns : <$x>"; 
		return $this; 
	}
	public function distinct($bool)
	{ 
		$this->distinctSelect = $bool; 
		return $this;
	}

	public function insert($graph)
	{ 
		$this->insertGraph = $graph;
		return $this;
	}

	public function delete($graph, $cond)
	{ 
		$this->deleteGraph = $graph;
		$this->deleteCond = $cond;
		return $this;
	}

	public function variable($x)
	{ 
		$this->variables[] = $x; 
		return $this;
	}
	public function where($x, $y, $z)
	{ 
		$this->wheres[] = "$x $y $z"; 
		return $this; 
	}
	public function optionalWhere($x, $y, $z)
	{ 
		$this->wheres[] = "OPTIONAL {".$x." ".$y." ".$z."}"; 
		return $this; 
	}
	public function optionalWhereComplexe($obj)
	{ 
		$this->wheres[] = "OPTIONAL ".$obj->buildWhere();
		return $this; 
	}
	public function union($x)
	{ 
		$this->unions[] = $x; 
		return $this; 
	}
	public function filter($x)
	{ 
		$this->wheres[] = "FILTER ($x)"; 
		return $this; 
	}
	public function orderBy($x)
	{ 
		$this->orders[] = $x;
		return $this; 
	}
	public function limit($x)
	{ 
		$this->limitNb = $x; 
		return $this; 
	}
	public function offset($x)
	{ 
		$this->offsetNb = $x; 
		return $this; 
	}
	
	public function launch($debug=false) 
	{
		if($this->sparql == "") $this->sparql = $this->build();
		
		if($debug) echo htmlspecialchars($this->sparql);
		//echo $this->sparql;
		
		$posts = array(
			$this->queryParam => urlencode($this->sparql),
			$this->formatParam => $this->format
		);
		
		$fields_string = "";
		
		//url-ify the data for the POST
		foreach($posts as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
		rtrim($fields_string,'&');
		
		$get = "";
		if($this->method == "GET") $get = "?".$fields_string;
		
		//open connection
		$ch = curl_init();
		
		//set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL,$this->baseUrl.$get);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		
		if($this->method != "GET")
		{
			curl_setopt($ch,CURLOPT_POST,count($posts));
			curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
		}
		
		//execute post
		$result = curl_exec($ch);
		
		//close connection
		curl_close($ch);
		
		if($this->format == 'json') return json_decode($result, true);
		else return $result;
	}
	
	
	private function build() 
	{
		$sp = "";

		//PREFIXES
		foreach($this->prefixes as $pre)
		{
			$sp .= $pre + "\n";
		}

		//VARIABLES
		if($this->insertGraph != null) $sp .= "INSERT IN GRAPH <" . $this->insertGraph . "> ";
		elseif($this->deleteGraph != null) $sp .= "WITH <" . $this->deleteGraph . "> DELETE { " . $this->deleteCond . " } ";
		else $sp .= "SELECT ";

		if($this->distinctSelect) $sp .= "DISTINCT ";

		if(count($this->variables) > 0 && $this->insertGraph == null && $this->deleteGraph == null)
		{
			$first = true;
			$i = 0;
			foreach($this->variables as $v)
			{
				if($first) $first = false;
				else if($i < count($this->variables)) $sp .= ", ";
				$sp .= $v;
				$i++;
			}
		}
		elseif($this->insertGraph == null && $this->deleteGraph == null) $sp .= "*";

		//WHERES 
		if($this->insertGraph == null) $sp .= "\nWHERE";

		if(count($this->unions) > 0) $sp .= "\n{";

		$w = $this->buildWhere();

		$sp .= $w;

		//UNIONS
		$first = true;
		foreach($this->unions as $v)
		{
			$u = $v->buildWhere();

			if($u != "")
			{			
				if($first)
				{
					$first = false;
					if($w != "") $sp .= "UNION";
				}
				else $sp .= "UNION";

				$sp .= $u;
			}
		}

		if(count($this->unions) > 0) $sp .= "\n}\n";

		//ORDER BY
		if(count($this->orders) > 0)
		{
			$sp .= 'ORDER BY ';
			$first = true;
			$i = 0;
			foreach($this->orders as $o)
			{
				if($first) $first = false;
				else if($i < count($this->orders)) $sp .= ", ";
				$sp .= $o;
				$i++;
			}
		}

		//LIMIT
		if($this->limitNb != null) $sp .= "\nLIMIT " . $this->limitNb;

		//OFFSET
		if($this->offsetNb != null) $sp .= "\nOFFSET " . $this->offsetNb;

		return $sp;
	}

	private function buildWhere() 
	{
		$sp = "";
		if(count($this->wheres) == 0) return $sp;

		$sp .= "\n{\n";
		
		$i = 0;
		foreach($this->wheres as $w)
		{
			$sp .= $w;
			if($i < count($this->wheres) - 1) $sp .= " .";

			$sp .= "\n";
			$i++;
		}

		$sp .= "}\n";

		return $sp;
	}
}