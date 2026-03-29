<?php

declare(strict_types=1);

namespace pocketmine\ddui;

final class DDUI{
	private function __construct(){
		//NOOP
	}

	public static function customForm() : CustomForm{
		return new CustomForm();
	}

	public static function messageBox() : MessageBox{
		return new MessageBox();
	}

	public static function observable(mixed $value) : Observable{
		return new Observable($value);
	}
}
