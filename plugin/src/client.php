<?php

declare(strict_types=1);

namespace SOFe\RpcGenerator;

use pocketmine\block\BlockFactory;
use pocketmine\utils\Binary;
use pocketmine\utils\BinaryStream;
use pocketmine\world\ChunkManager;
use RuntimeException;
use function strlen;
use function substr;

final class Client {
    private string $buffer = "";

    public function __construct(private ChunkManager $world) {
    }

    public function write(string $data) : void {
        $this->buffer .= $data;
        while (true) {
            if (!$this->flush()) {
                break;
            }
        }
    }

    public function close() : void {
        $this->flush();
        while (true) {
            if (!$this->flush()) {
                break;
            }
        }
    }

    private function flush() : bool {
        if (strlen($this->buffer) >= 4) {
            $length = Binary::readLInt(substr($this->buffer, 0, 4));
            if ($length < 0) {
                throw new RuntimeException("Invalid data");
            }

            if (strlen($this->buffer) - 4 >= $length) {
                $this->executeSection(substr($this->buffer, 4, $length));
                $this->buffer = substr($this->buffer, 4 + $length);
                return true;
            }
        }

        return false;
    }

    private function executeSection(string $buffer) : void {
        $stream = new BinaryStream($buffer);

        $startX = $stream->getUnsignedVarInt();
        $startY = $stream->getUnsignedVarInt();
        $startZ = $stream->getUnsignedVarInt();
        $endX = $stream->getUnsignedVarInt();
        $endY = $stream->getUnsignedVarInt();
        $endZ = $stream->getUnsignedVarInt();

        for ($x = $startX; $x < $endX; $x++) {
            for ($y = $startY; $y < $endY; $y++) {
                for ($z = $startZ; $z < $endZ; $z++) {
                    $id = $stream->getUnsignedVarInt();
                    $meta = $stream->getUnsignedVarInt();
                    $this->world->setBlockAt($x, $y, $z, BlockFactory::getInstance()->get($id, $meta));
                }
            }
        }
    }
}
