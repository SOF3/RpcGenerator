<?php

declare(strict_types=1);

namespace SOFe\RpcGenerator;

use InvalidArgumentException;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Internet;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\GeneratorManager;
use function is_object;
use function is_string;
use function json_decode;
use function json_encode;
use function parse_url;
use function sprintf;
use function urlencode;

final class Main extends PluginBase {
    protected function onEnable() : void {
        $this->saveDefaultConfig();

        GeneratorManager::getInstance()->addGenerator(RpcGenerator::class);
    }
}

final class RpcGenerator extends Generator {
    private string $address;
    private string $payload;

    public function __construct(int $seed, string $preset) {
        if ($preset[0] === "{") {
            $data = json_decode($preset);

            if (!is_object($data)) {
                throw new InvalidArgumentException("Invalid preset: invalid JSON");
            }

            if (!is_object($data) || !isset($data->address) || !is_string($data->address)) {
                throw new InvalidArgumentException("Invalid preset: missing address field");
            }

            $address = self::validateAddress($data->address, $seed);

            $this->address = $address;
            $this->payload = json_encode($data["payload"], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            $address = self::validateAddress($preset, $seed);
            $this->address = $address;
            $this->payload = "{}";
        }
    }

    private static function validateAddress(string $address, int $seed) : string {
        $url = parse_url($address);

        if ($url["scheme"] !== "http" && $url["scheme"] !== "https") {
            throw new InvalidArgumentException("Invalid preset: invalid scheme, only http and https allowed");
        }

        $address .= isset($url["query"]) ? "&" : "?";

        if (isset($url["fragment"])) {
            throw new InvalidArgumentException("Invalid preset: fragment section not allowed in URL");
        }

        $address .= "seed=" . urlencode((string) $seed);
        return $address;
    }

    public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void {
        $this->call($world, "generateChunk", $chunkX, $chunkZ);
    }
    public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void {
        $this->call($world, "generateChunk", $chunkX, $chunkZ);
    }

    private function call(ChunkManager $world, string $action, int $chunkX, int $chunkZ) : void {
        $url = sprintf("%s&action=%s&chunkX=%s&chunkZ=%s", $this->address, urlencode($action), urlencode((string) $chunkX), urlencode((string) $chunkZ));

        $client = new Client($world, $chunkX, $chunkZ);
        Internet::simpleCurl($url, 0, [
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $this->payload,
            CURLOPT_FORBID_REUSE => 0,
            CURLOPT_FRESH_CONNECT => 0,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_BUFFERSIZE => 1024,
            CURLOPT_WRITEFUNCTION => function($_ch, string $data) use ($client) : void {
                $client->write($data);
            },
        ]);
        $client->close();
    }
}
