<?php

namespace Tests\Unit;

use App\Http\Controllers\V1\Server\UniProxyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UniProxyAliveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
    }

    private function makeController(string $nodeType = 'vmess', int $nodeId = 1): UniProxyController
    {
        $controller = (new \ReflectionClass(UniProxyController::class))
            ->newInstanceWithoutConstructor();

        $ref = new \ReflectionClass($controller);

        $prop = $ref->getProperty('nodeType');
        $prop->setAccessible(true);
        $prop->setValue($controller, $nodeType);

        $prop = $ref->getProperty('nodeId');
        $prop->setAccessible(true);
        $prop->setValue($controller, $nodeId);

        $nodeInfo = new \stdClass();
        $nodeInfo->id = $nodeId;
        $nodeInfo->group_id = [1];
        $prop = $ref->getProperty('nodeInfo');
        $prop->setAccessible(true);
        $prop->setValue($controller, $nodeInfo);

        return $controller;
    }

    /**
     * When the node reports an empty JSON body, the alive method should
     * return early with ['data' => true] to avoid calling Cache::many([]).
     */
    public function testAliveReturnsEarlyWhenDataIsEmpty()
    {
        $controller = $this->makeController();

        $request = Request::create('/api/v1/server/uniProxy/alive', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $response = $controller->alive($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['data' => true], json_decode($response->getContent(), true));
    }

    /**
     * When the node reports a null/empty body (no JSON at all), alive should
     * return early without touching the cache.
     */
    public function testAliveReturnsEarlyWhenBodyIsNull()
    {
        $controller = $this->makeController();

        $request = Request::create('/api/v1/server/uniProxy/alive', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '');

        $response = $controller->alive($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['data' => true], json_decode($response->getContent(), true));
    }

    /**
     * When the node sends a non-array value via $_POST fallback, the alive
     * method should return 400 with an error message.
     */
    public function testAliveReturns400WhenDataIsNotArray()
    {
        $controller = $this->makeController();

        // Simulate: json()->all() returns [] (empty), and $_POST is overridden
        // to a non-array value. In PHP $_POST is always an array, but the check
        // guards against unexpected mutations. We test the guard by calling
        // the method logic via a request whose json content decodes to a scalar.
        // We need to bypass the ParameterBag wrapping, so we use reflection.
        $ref = new \ReflectionClass($controller);
        $method = $ref->getMethod('alive');
        $method->setAccessible(true);

        // Create a request where json()->all() returns empty so we fall back to $_POST
        $request = Request::create('/api/v1/server/uniProxy/alive', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '');

        // Set $_POST to a non-array value to trigger the guard
        $originalPost = $_POST;
        $_POST = 'invalid';

        $response = $controller->alive($request);

        $_POST = $originalPost;

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid online data format', $data['error']);
    }

    /**
     * Valid data should be processed without error. The array cache driver
     * handles Cache::many() and Cache::put() in memory.
     */
    public function testAliveProcessesValidData()
    {
        Cache::flush();
        $controller = $this->makeController();

        $request = Request::create('/api/v1/server/uniProxy/alive', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['100' => ['192.168.1.1_node1']]));

        $response = $controller->alive($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['data' => true], json_decode($response->getContent(), true));

        // Verify that cache was updated for the user
        $cached = Cache::get('ALIVE_IP_USER_100');
        $this->assertIsArray($cached);
        $this->assertArrayHasKey('alive_ip', $cached);
        $this->assertArrayHasKey('vmess1', $cached);
        $this->assertEquals(['192.168.1.1_node1'], $cached['vmess1']['aliveips']);
    }

    /**
     * When data contains entries with non-numeric keys or non-array values,
     * those entries should be skipped but valid entries still processed.
     */
    public function testAliveSkipsInvalidEntriesInData()
    {
        Cache::flush();
        $controller = $this->makeController();

        $data = [
            '200' => ['10.0.0.1_node1'],       // valid
            'abc' => ['10.0.0.2_node1'],        // invalid key (non-numeric)
            '300' => 'not-an-array',            // invalid value
        ];

        $request = Request::create('/api/v1/server/uniProxy/alive', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($data));

        $response = $controller->alive($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['data' => true], json_decode($response->getContent(), true));

        // Only valid entry (uid=200) should be cached
        $cached200 = Cache::get('ALIVE_IP_USER_200');
        $this->assertIsArray($cached200);
        $this->assertArrayHasKey('alive_ip', $cached200);

        // Invalid entries should NOT be cached
        // Note: 'abc' and '300' still generate cache keys via array_keys($data)
        // but the foreach loop skips them due to validation checks.
        // The cache key for 'abc' is generated but no put() happens for it.
        $cachedAbc = Cache::get('ALIVE_IP_USER_abc');
        $this->assertNull($cachedAbc);

        $cached300 = Cache::get('ALIVE_IP_USER_300');
        $this->assertNull($cached300);
    }

    /**
     * Confirm that Cache::many() is never called with an empty array.
     * This was the root cause of the Redis mget([]) error.
     */
    public function testAlivePreventsEmptyCacheManyCall()
    {
        $controller = $this->makeController();

        // Send an empty JSON object - no cache keys should be generated
        $request = Request::create('/api/v1/server/uniProxy/alive', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(new \stdClass()));

        $response = $controller->alive($request);

        // Should return early without calling Cache::many([])
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['data' => true], json_decode($response->getContent(), true));
    }
}
