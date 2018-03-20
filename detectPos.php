<?php


Class detectPos
{
	protected $dict;
	protected $model;
	protected $multimodel;
	public $listPos;
	public $listCombs;
	public $listProbs;
	public $topComb;
	public $deconstrSentence;
	public $status;
	
	protected $contraction = Array( //http://grammar.yourdictionary.com/style-and-usage/using-contractions.html https://dictionary.cambridge.org/grammar/british-grammar/writing/contractions
		"m" => "am",
		"t" => "not",
		"ve" => "have",
		"ll" => "will",
		"s" => "is",
		"d" => "would",
		"t" => "not",
		"re" => "are"
	);
	
	public function __construct() {
		$this->dict = json_decode(file_get_contents("dict/dict.json"), true);
		$this->model = json_decode(file_get_contents("model/model.json"), true);
		$this->multimodel = json_decode(file_get_contents("model/multimodel.json"), true);
	}
	
	protected function getCombinations($arrays) { //https://gist.github.com/cecilemuller/4688876
		$nn = 0;
		foreach($arrays as $rrr) {
			$nn = $nn + count($rrr);
		}
		if($nn < 47) {
			$result = array(array());
			foreach ($arrays as $property => $property_values) {
				$tmp = array();
				foreach ($result as $result_item) {
					foreach ($property_values as $property_value) {
						$tmp[] = array_merge($result_item, array($property => $property_value));
					}
				}
				$result = $tmp;
			}
			return $result;
		} else {
			return false;
		}
		
	}
	
	protected function processSentence(&$sentence) {
		$sentence = strtolower($sentence);
		$sentence = str_replace(array('.', ','), '' , $sentence);
		$sentence = explode(" ", $sentence);
		$result = Array();
		foreach($sentence as $word) {
			if(strpos($word, "'") != FALSE) {
				$word = explode("'", $word);
				if($word[1] != "t") {
					$result[] = $word[0];
				} else {
					$result[] = substr($word[0], 0, -1);
				}
				$result[] = $this->contraction[$word[1]];
			} else {
				$result[] = $word;
			}
		}
		$sentence = $result;
	}
	
	protected function retrievePos($sentence) {
		$dict = $this->dict;
		$result = Array();
		foreach($sentence as $key => $word) {
			if(array_key_exists($word, $dict)) {
				$result[$key] = $dict[$word]["pos"];
			} else {
				$result[$key] = Array("noun", "verb", "adv", "adj", "conj", "prep", "pnoun"); // POS to add if none is known
			}
		}
		$dict = null;
		return $result;
	}
	
	public function detect($sentence) {
		$this->status = true;
		$model = $this->model;
		$multimodel = $this->multimodel;
		$this->processSentence($sentence);
		$this->deconstrSentence = $sentence;
		$result = $this->retrievePos($sentence);
		$this->listPos = $result;	
		$sentence_strut = Array();

		foreach($sentence as $key => $word) {
			$sentence_strut[$word] = $result[$key];
		}
		$this->listPos = $sentence_strut;
		$pos_combs = $this->getCombinations($result);
		if($pos_combs != false) {
			$this->listCombs = $pos_combs;

			$pos_combs_numerical = Array();
			foreach($pos_combs as $key => $comb) {
				foreach($comb as $pos) {
					$pos_combs_numerical[$key][] = $pos;
				}
			}

			$probabilities = Array();
			foreach($pos_combs_numerical as $key => $comb) {
				$prob = 1;
				$i = 0;
				foreach($comb as $w_key => $pos) {
					if($i > 0) {
						if(($i > 2) && ($pos == $comb[$w_key-1]) && ($pos == $comb[$w_key-2]) && ($pos == $comb[$w_key-3])) {
							$model_prob = $multimodel[$pos][3];
						} elseif (($i > 1) && ($pos == $comb[$w_key-1]) && ($pos == $comb[$w_key-2])) {
							$model_prob = $multimodel[$pos][2];
						} elseif ($pos == $comb[$w_key-1]) {
							$model_prob = $multimodel[$pos][1];
						} else {
							$model_prob = $model[$comb[$w_key-1]][$pos];
						}
						$prob = $prob * $model_prob;
					}
					$i++;
				}
				$probabilities[$key] = $prob;
			}
			arsort($probabilities);
			$this->listProbs = $probabilities;
			
			reset($probabilities);
			$topKey = key($probabilities);
			$this->topComb = $this->listCombs[$topKey]
			return $this->listCombs[$topKey];
		} else {
			$this->status = false;
		}
	}
}


?>