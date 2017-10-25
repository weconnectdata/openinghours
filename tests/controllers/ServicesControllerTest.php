<?php

namespace Tests\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;

class ServicesControllerTest extends \TestCase
{
    use DatabaseTransactions;

    /**
     * @var string
     */
    protected $apiUrl = '/api/services';
    /**
     * @test
     */
    public function testGetServicesWithoutAuth()
    {
        $call = $this->doRequest('GET', $this->apiUrl);
        $content = $this->getContentStructureTested($call);
        $this->assertCount(count(\App\Models\Service::all()), $content);
    }

    /**
     * @test
     */
    public function testGetServicesAsAdminRole()
    {
        $user = \App\Models\User::find(1);
        $this->actingAs($user, 'api');

        $call = $this->doRequest('GET', $this->apiUrl);
        $content = $this->getContentStructureTested($call);
        $this->assertCount(count(\App\Models\Service::all()), $content);
    }

    /**
     * @test
     */
    public function testGetServicesAsMemberRole()
    {
        $user = \App\Models\User::find(2);
        $this->actingAs($user, 'api');

        $call = $this->doRequest('GET', $this->apiUrl);
        $content = $this->getContentStructureTested($call);
        $this->assertCount(count(\App\Models\Service::all()), $content);
    }

    /**
     * Data provider for requests
     *
     * Datastructure:
     * ['userId', verb', 'uri', 'data', 'responce status'] // Resource controller action
     * @return array
     */
    public function requestTypeProvider()
    {
        return [
            //  unauth user
            ['', 'get', '', [], '200'], // index
            ['', 'post', '', [], '405'], // store
            ['', 'get', '1', [], '200'], // show
            ['', 'put', '1', [], '405'], // update (full)
            ['', 'patch', '1', ['draft' => false], '405'], // update (partial)
            ['', 'delete', '1', [], '405'], // destroy
            // admin user
            ['1', 'get', '', [], '200'], // index
            ['1', 'post', '', [], '405'], // store
            ['1', 'get', '1', [], '200'], // show
            ['1', 'put', '1', [], '405'], // update (full)
            ['1', 'patch', '1', ['draft' => false], '405'], // update (partial)
            ['1', 'delete', '1', [], '405'], // destroy
            // regular user
            ['2', 'get', '', [], '200'], // index
            ['2', 'post', '', [], '405'], // store
            ['2', 'get', '1', [], '200'], // show
            ['2', 'put', '1', [], '405'], // update (full)
            ['2', 'patch', '1', ['draft' => false], '405'], // update (partial)
            ['2', 'delete', '1', [], '405'], // destroy
        ];
    }

    /**
     * @test
     * @dataProvider requestTypeProvider
     */
    public function testRequestsByUserWithRoleAndCheckStatusCode($userId, $verb, $pathArg, $data, $statusCode)
    {
        if ($userId) {
            $authUser = \App\Models\User::find($userId);
            $this->actingAs($authUser, 'api');
        }
        $path = $this->assemblePath($pathArg);
        $this->doRequest($verb, $path);
    }

    /**
     * assemble the path on the given params
     */
    protected function assemblePath($params)
    {
        return $this->apiUrl . '/' . $params;
    }
}
