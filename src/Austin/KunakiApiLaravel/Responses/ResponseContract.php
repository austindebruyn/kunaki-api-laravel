<?php namespace Austin\KunakiApiLaravel\Responses;

interface ResponseContract {

    /**
     * Gets the order id.
     *
     * @return integer
     */
    public function getId();
}