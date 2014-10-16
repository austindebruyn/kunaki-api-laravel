<?php namespace Austin\KunakiApiLaravel\Responses;


class XmlResponse implements ResponseContract {

    /**
     * Order ID.
     *
     * @var integer
     */
    private $id;

    /**
     * Constructor.
     *
     * @param integer $id
     */
    public function __construct($id = -1)
    {
        $this->id = $id;
    }

    /**
     * Gets the order id.
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

} 