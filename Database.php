<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    function keyConvert($keyword, $types)
    {
        $sql = [];
        if (is_null($keyword) && (isset($types['null']))){
            $sql[] = 'NULL';
        }elseif (is_bool($keyword) && (isset($types['bool']))){
            if ($keyword == true) $sql[] = 1; else $sql[] = 0;
        }elseif (is_int($keyword) && (isset($types['int']))){
            $sql[] = (int)$keyword;
        }elseif (is_float($keyword) && (isset($types['float']))){
            $sql[] = (float)$keyword;
        }elseif (is_string($keyword) && (isset($types['string']))){
            $sql[] = "'".$keyword."'";
        }else{
            throw new Exception('Неверно переданные параметры!');
        }
        return implode(", ", $sql);
    }

    public function testData($keywords, array $types = ['array' => false, 'array_index' => false, 'string' => false, 'int' => false, 'float' => false, 'bool' => false, 'null' => false])
    {
        $sql = [];
        if (is_string($keywords) && (isset($types['array_index']))){
            return "`$keywords`";
        }
        if(is_array($keywords)){
            foreach($keywords as $key => $keyword){
                if (is_int($key) && (isset($types['array']))){
                    $sql[] = "$keyword";
                }elseif (is_string($key) && (isset($types['array']))){
                        $sql[] = "`".$key."` = ".$this->keyConvert($keyword, $types)."";
                }elseif (is_string($keyword) && (isset($types['array_index']))){
                    $sql[] = "`$keyword`";
                }
            }
            return implode(', ', $sql);
        }
        
        return $this->keyConvert($keywords, $types, $sql);
    }

    public function buildQuery(string $query, array $args = []): string
    {
        preg_match_all('(\?.)', $query, $matches, PREG_OFFSET_CAPTURE);
        $rev_matche = array_reverse($matches[0]);
        $args = array_reverse($args);

        foreach($rev_matche as $key => $rev_matche){
            switch ($rev_matche[0]){
                case '? ':
                    //Простая замена | (NULL)
                    // is_string, is_int, is_float, is_bool (приводится к 0 или 1) и is_null
                    $str = $this->testData($args[$key], ['string' => true, 'int' => true, 'float' => true, 'bool' => true, 'null' => true]);
                    $query = substr_replace($query, $str, $rev_matche[1], 1);
                    break;
                case '?d':
                    //Конвертация в целое число | (NULL)
                    $str = $this->testData($args[$key], ['int' => true, 'bool' => true, 'null' => true]);
                    $query = substr_replace($query, $str, $rev_matche[1], 2);
                    break;
                case '?f':
                    //конвертация в число с плавающей точкой | (NULL)
                    $str = $this->testData($args[$key], ['floor' => true, 'null' => true]);
                    $query = substr_replace($query, $str, $rev_matche[1], 2);
                    // var_dump($matche[0]);
                    break;
                case '?a':
                    //массив значений
                    // Массив (параметр ?a) преобразуется либо в список значений через запятую (список), либо в пары идентификатор и значение через запятую (ассоциативный массив).
                    //Каждое значение из массива форматируется в зависимости от его типа (идентично универсальному параметру без спецификатора).
                    $str = $this->testData($args[$key], ['string' => true, 'int' => true, 'float' => true, 'bool' => true, 'null' => true, 'array' => true]);
                    $query = substr_replace($query, $str, $rev_matche[1], 2);
                    break;
                case '?#':
                    //идентификатор или массив идентификаторов
                    $str = $this->testData($args[$key], ['string' => true, 'int' => true, 'float' => true, 'bool' => true, 'null' => true, 'array_index' => true]);
                    $query = substr_replace($query, $str, $rev_matche[1], 2);
                    break;
                break;
            }
        }
        preg_match('#\{(.*?)\}#', $query, $mah);
        if (isset($mah[1])) {
            if(str_contains($mah[1], " = NULL")){
                $query = str_replace($mah[0], '', $query);
            }else{
                $query = str_replace($mah[0], $mah[1], $query);
            }
        }
        return $query;
    }

    public function skip()
    {
        return;
    }
}
