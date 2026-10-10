<?php

namespace App\Services\ClickHouse;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ClickHouseClient
{
    public function __construct(protected array $config) {}

    public static function fromConfig(): static
    {
        return new static(config('loggerxo.clickhouse'));
    }

    public function database(): string
    {
        return $this->config['database'];
    }

    public function table(string $name): string
    {
        return $this->config['tables'][$name] ?? $name;
    }

    protected function baseUrl(): string
    {
        return sprintf('%s://%s:%d/', $this->config['protocol'] ?? 'http', $this->config['host'], $this->config['port']);
    }

    protected function http(array $query = []): PendingRequest
    {
        $query = array_merge([
            'database' => $this->config['database'],
            'date_time_input_format' => 'best_effort',
            'output_format_json_quote_64bit_integers' => 0,
        ], $query);

        return Http::withBasicAuth($this->config['username'], $this->config['password'])
            ->timeout($this->config['timeout'] ?? 30)
            ->connectTimeout(5)
            ->withHeaders(['Content-Type' => 'text/plain; charset=UTF-8'])
            ->baseUrl($this->baseUrl())
            ->withQueryParameters($query);
    }

    protected function paramQuery(array $params): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 1 : 0;
            } elseif (is_array($value)) {
                $value = $this->encodeArrayParam($value);
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s.v');
            }
            $out['param_'.$key] = (string) $value;
        }

        return $out;
    }

    protected function encodeArrayParam(array $values): string
    {
        $parts = array_map(function ($v) {
            if (is_int($v) || is_float($v)) {
                return (string) $v;
            }

            return "'".str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $v)."'";
        }, array_values($values));

        return '['.implode(',', $parts).']';
    }

    /**
     * Run a SELECT and return rows as associative arrays.
     * Parameters use ClickHouse syntax: {name:UInt64}, {name:String}, {name:Array(String)} ...
     */
    public function select(string $sql, array $params = []): array
    {
        $sql = rtrim(trim($sql), ';').' FORMAT JSON';
        $response = $this->http($this->paramQuery($params))->withBody($sql, 'text/plain')->post('');
        $this->guard($response, $sql);
        $json = $response->json();

        return $json['data'] ?? [];
    }

    public function selectOne(string $sql, array $params = []): ?array
    {
        $rows = $this->select($sql, $params);

        return $rows[0] ?? null;
    }

    public function scalar(string $sql, array $params = [], mixed $default = null): mixed
    {
        $row = $this->selectOne($sql, $params);
        if (! $row) {
            return $default;
        }

        return array_values($row)[0] ?? $default;
    }

    /**
     * Run a statement that does not return rows (DDL, DELETE, ALTER ...).
     */
    public function execute(string $sql, array $params = []): void
    {
        $response = $this->http($this->paramQuery($params))->withBody(trim($sql), 'text/plain')->post('');
        $this->guard($response, $sql);
    }

    /**
     * Bulk insert rows (list of associative arrays) using JSONEachRow.
     */
    public function insert(string $table, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }
        $body = '';
        foreach ($rows as $row) {
            $body .= json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR)."\n";
        }
        $response = $this->http([
            'query' => sprintf('INSERT INTO %s FORMAT JSONEachRow', $table),
            'input_format_skip_unknown_fields' => 1,
            'input_format_import_nested_json' => 0,
            'input_format_null_as_default' => 1,
        ])->withBody($body, 'application/x-ndjson')->post('');
        $this->guard($response, 'INSERT INTO '.$table);

        return count($rows);
    }

    public function ping(): bool
    {
        try {
            return trim((string) $this->http()->withBody('SELECT 1', 'text/plain')->post('')->body()) === '1';
        } catch (\Throwable) {
            return false;
        }
    }

    protected function guard(Response $response, string $sql): void
    {
        if ($response->successful()) {
            return;
        }
        $body = trim($response->body());
        throw new ClickHouseException(sprintf(
            'ClickHouse error %d: %s | SQL: %s',
            $response->status(),
            mb_substr($body, 0, 1500),
            mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 500)
        ));
    }
}
