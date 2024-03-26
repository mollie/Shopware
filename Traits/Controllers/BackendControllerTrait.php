<?php


namespace MollieShopware\Traits\Controllers;

trait BackendControllerTrait
{

    /**
     * Return success JSON
     *
     * @param $message
     * @param $data
     */
    protected function returnSuccess($message, $data)
    {
        $this->returnJson([
            'success' => true,
            'message' => addslashes($message),
            'data' => $data
        ]);
    }

    /**
     * Return success JSON
     *
     * @param $message
     */
    protected function returnError($message)
    {
        $this->returnJson([
            'success' => false,
            'message' => addslashes($message),
        ]);
    }

    /**
     * Return JSON
     *
     * @param $data
     * @param int $httpCode
     */
    protected function returnJson($data, $httpCode = 200)
    {
        if ($httpCode !== 200) {
            $this->Response()->setHttpResponseCode((int)$httpCode);
        }
        $this->View()->assign($data);
    }
}
