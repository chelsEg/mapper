<?php

namespace Tarantool\Mapper\Contracts;

use Tarantool\Client;

interface Manager
{
    /**
     * @return Repository|Entity
     */
    public function get($type, $id = null);

    /**
     * @return Entity
     */
    public function save(Entity $entity);

    /**
     * @return Entity
     */
    public function make($type, $data);

    /**
     * @return Client
     */
    public function getClient();

    /**
     * @return Schema
     */
    public function getSchema();

    /**
     * @return Meta
     */
    public function getMeta();
}
