<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * BedrockProtocol in this tree ships DDUI packets, but two classes miss the
 * direction marker interfaces that NetworkSession expects for send/receive.
 * Patch them after composer install so DDUI packets can flow through PMMP.
 *
 * @param array<string, string> $replacements
 */
function patchFile(string $path, array $replacements) : void{
	if(!file_exists($path)){
		echo "Skipping missing file: {$path}\n";
		return;
	}

	$contents = file_get_contents($path);
	if($contents === false){
		throw new RuntimeException(sprintf('Failed to read "%s"', $path));
	}

	$patched = $contents;
	$changed = false;

	foreach($replacements as $search => $replace){
		if(str_contains($patched, $replace)){
			continue;
		}

		if(!str_contains($patched, $search)){
			throw new RuntimeException(sprintf('Expected token was not found in "%s": %s', $path, $search));
		}

		$patched = str_replace($search, $replace, $patched);
		$changed = true;
	}

	if(!$changed){
		echo "Already patched: {$path}\n";
		return;
	}

	if(file_put_contents($path, $patched) === false){
		throw new RuntimeException(sprintf('Failed to write "%s"', $path));
	}

	echo "Patched: {$path}\n";
}

$protocolSrc = dirname(__DIR__) . '/vendor/pocketmine/bedrock-protocol/src';

patchFile(
	$protocolSrc . '/ClientboundDataStorePacket.php',
	[
		'class ClientboundDataStorePacket extends DataPacket{' => 'class ClientboundDataStorePacket extends DataPacket implements ClientboundPacket{',
	]
);

patchFile(
	$protocolSrc . '/ServerboundDataDrivenScreenClosedPacket.php',
	[
		'class ServerboundDataDrivenScreenClosedPacket extends DataPacket{' => 'class ServerboundDataDrivenScreenClosedPacket extends DataPacket implements ServerboundPacket{',
	]
);

patchFile(
	$protocolSrc . '/types/DataStoreChange.php',
	[
		'use pmmp\\encoding\\VarInt;' => 'use pmmp\\encoding\\LE;',
		'$updateCount = VarInt::readUnsignedInt($in);' => '$updateCount = LE::readUnsignedInt($in);',
		'$data = match(VarInt::readUnsignedInt($in)){' . "\n" .
			"\t\t\t" . 'DataStoreValueType::DOUBLE => DoubleDataStoreValue::read($in),' . "\n" .
			"\t\t\t" . 'DataStoreValueType::BOOL => BoolDataStoreValue::read($in),' . "\n" .
			"\t\t\t" . 'DataStoreValueType::STRING => StringDataStoreValue::read($in),' . "\n" .
			"\t\t\t" . 'default => throw new PacketDecodeException("Unknown DataStoreValueType"),' . "\n" .
			"\t\t" . '};'
			=> '$data = match(LE::readSignedInt($in)){' . "\n" .
			"\t\t\t" . 'BoolDataStoreValue::ID => BoolDataStoreValue::read($in),' . "\n" .
			"\t\t\t" . 'LongDataStoreValue::ID => LongDataStoreValue::read($in),' . "\n" .
			"\t\t\t" . 'StringDataStoreValue::ID => StringDataStoreValue::read($in),' . "\n" .
			"\t\t\t" . 'TypeDataStoreValue::ID => TypeDataStoreValue::read($in),' . "\n" .
			"\t\t\t" . 'default => throw new PacketDecodeException("Unknown DataStoreValueType"),' . "\n" .
			"\t\t" . '};',
		'VarInt::writeUnsignedInt($out, $this->updateCount);' => 'LE::writeUnsignedInt($out, $this->updateCount);',
		'VarInt::writeUnsignedInt($out, $this->data->getTypeId());' => 'LE::writeSignedInt($out, $this->data->getTypeId());',
	]
);
