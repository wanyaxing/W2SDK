<?php
/**
 * 数学相关处理函数库文件
 * @package W2
 * @author axing
 * @since 1.0
 * @version 1.0
 */
class W2Math {

	/**
	 * 取得某个整数的位子集
	 * @param  int $number    目标数字
	 * @return array        由1248等组成的数组
	 */
	public static function getBitsOfNumber($number)
	{
		$bits = array();
		for ($i = 1 ; $i<=$number ; $i = $i * 2)
		{
			if ( ($i & $number) == $i)
			{
				$bits[] = $i;
			}
		}
		return $bits;
	}

	/**
	 * 取得目标数字的所有子集
	 * @param  int $number     目标数字
	 * @return array         由1...等组成的数组
	 */
	public static function getChildsOfNumber($number)
	{
		$childs = array();
		for ($i = $number ; $i ; $i = ($i - 1) & $number)
		{
			$childs[] = $i;
		}
		return $childs;
	}

	/**
	 * 判断x是否y的子集
	 * @param  int  $x    数字
	 * @param  int  $y    数字
	 * @return boolean
	 */
	public static function isXinY($x,$y)
	{
		return ($x & $y) == $x;
	}


	/** 取得数字的精确位，正数表示n位小数，负数表示精确到个十百千万位（10的(n-1)次方） */
	public static function getPrecisionOfNumber($number)
	{
		$number = abs($number);
		$len = strlen($number);
		if (strpos($number,'.')>=0)
		{
			return $len - strpos($number,'.') + 1;
		}
		else
		{
			for ($i=1; $i < $len; $i++)
			{
				if (substr($number,$len-$i,1) > 0)
				{
					return 0 - ($i - 1);
				}
			}
		}
	}


	/**
	 * 四舍五入，保留n位小数，也支持n是负数哦，如round(198542,-3)=>199000
	 * @param  float  $val         值
	 * @param  integer $precision  精确位
	 * @param  integer  $mode      精确模式
	 * @return float              值
	 */
	public static function round( $val ,  $precision = 0 ,  $mode = PHP_ROUND_HALF_UP  )
	{
		return round($val,$precision,$mode);
	}

	/**
	 * 取得两个数字的模糊中间数，并尽可能的忽略精确值。比如0.99与1.2的中间数是1
	 * @param  [type]  $bigNumber          [description]
	 * @param  [type]  $smallNumber        [description]
	 * @param  boolean $isShortIfShortAble [description]
	 * @return [type]                      [description]
	 */
	public static function getMiddleBetweenNumbers($bigNumber=null,$smallNumber=null)
	{
		if (!is_null($bigNumber) || !is_null($smallNumber))
		{
			if (is_null($bigNumber))
			{
				$precision = min(W2Math::getPrecisionOfNumber($smallNumber),-1);
				return $smallNumber + pow(10,abs($precision));
			}
			else if (is_null($smallNumber))
			{
				$precision = min(W2Math::getPrecisionOfNumber($bigNumber),-1);
				return $bigNumber - pow(10,abs($precision));
			}
			else if ($bigNumber==$smallNumber)
			{
				return $bigNumber;
			}
			else if ($bigNumber<$smallNumber)
			{
				return null;
			}
			else
			{
				$middle = $smallNumber + (($bigNumber - $smallNumber)/2);
				$precisionMin = min(W2Math::getPrecisionOfNumber($bigNumber),W2Math::getPrecisionOfNumber($smallNumber),-1);
				$precisionMax = max(W2Math::getPrecisionOfNumber($bigNumber),W2Math::getPrecisionOfNumber($smallNumber),$precisionMin);
				for ($i=$precisionMin; $i <=$precisionMax ; $i++) {
					$tmp = round($middle,$i);
					if ($tmp>$smallNumber && $tmp<$bigNumber)
					{
						return $tmp;
					}
				}
			}
		}
		return null;
	}

}
