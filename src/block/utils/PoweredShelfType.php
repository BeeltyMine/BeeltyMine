<?php

/*
 *
 * $$$$$$$\                      $$\   $$\               $$\      $$\ $$\                     
 * $$  __$$\                     $$ |  $$ |              $$$\    $$$ |\__|                    
 * $$ |  $$ | $$$$$$\   $$$$$$\  $$ |$$$$$$\   $$\   $$\ $$$$\  $$$$ |$$\ $$$$$$$\   $$$$$$\  
 * $$$$$$$\ |$$  __$$\ $$  __$$\ $$ |\_$$  _|  $$ |  $$ |$$\$$\$$ $$ |$$ |$$  __$$\ $$  __$$\ 
 * $$  __$$\ $$$$$$$$ |$$$$$$$$ |$$ |  $$ |    $$ |  $$ |$$ \$$$  $$ |$$ |$$ |  $$ |$$$$$$$$ |
 * $$ |  $$ |$$   ____|$$   ____|$$ |  $$ |$$\ $$ |  $$ |$$ |\$  /$$ |$$ |$$ |  $$ |$$   ____|
 * $$$$$$$  |\$$$$$$$\ \$$$$$$$\ $$ |  \$$$$  |\$$$$$$$ |$$ | \_/ $$ |$$ |$$ |  $$ |\$$$$$$$\ 
 * \_______/  \_______| \_______|\__|   \____/  \____$$ |\__|     \__|\__|\__|  \__| \_______|
 *                                            $$\   $$ |                                     
 *                                            \$$$$$$  |                                     
 *                                             \______/                                      
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Ayrz
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\block\utils;

enum PoweredShelfType : int{
	case UNCONNECTED = 0;
	case RIGHT = 1;
	case CENTER = 2;
	case LEFT = 3;
}
