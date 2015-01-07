<?php

/**
 * class RtRestClient
 * C. Loos <cloos@netsandbox.de>
 */

require_once 'HTTP/Request.php';
 
class RtRestClient
{
    public $error;

    private $rest_url;
    private $request;
    private $cookie;

    public function login( $url, $user, $pass )
    {
        $this->rest_url = $url . 'REST/1.0/';
        $this->request =& new HTTP_Request();
        $this->request->setUrl( $this->rest_url );
        $this->request->addQueryString( 'user', $user );
        $this->request->addQueryString( 'pass', $pass );
        $this->request->sendRequest();

        $response_code = $this->request->getResponseCode();
        $response_body = $this->request->getResponseBody();
        list( $code, $msg, $content ) = $this->splitResponseBody( $response_body );
        if( $response_code != 200 )
        {
            $this->error = $response_body;
            return false;
        }
        if( $code != 200 )
        {
            $this->error = "$msg\n$content";
            return false;
        }
        $cookies = $this->request->getResponseCookies();
        $this->cookie = $cookies[0];
        $this->request->addCookie( $this->cookie['name'], $this->cookie['value'] );

        return true;
    }

    public function logout()
    {
        $url = $this->rest_url . 'logout';
        $this->request->setURL( $url );
        $this->request->clearPostData();
        $this->request->sendRequest();
    }

    public function search( $type, $query, $format = 's')
    {
        switch( $type )
        {
            case 'ticket':
                $url = $this->rest_url . 'search/ticket';
                $this->request->setURL( $url );
                $this->request->clearPostData();
                $this->request->addQueryString( 'query' , $query );
                $this->request->addQueryString( 'format' , $format );
                $this->request->sendRequest();

                $response_code = $this->request->getResponseCode();
                $response_body = $this->request->getResponseBody();

                list( $code, $msg, $content ) = $this->splitResponseBody( $response_body );
                if( $response_code != 200 )
                {
                    $this->error = $response_body;
                    return false;
                }
                if( $code != 200 )
                {
                    $this->error = "$msg\n$content";
                    return false;
                }

                $return_array = array();

                if( $content == 'No matching results.' )
                {
                    return $return_array;
                }

                if( $format == 's' )
                {
                    $results = preg_split( '/\n/', $content );
                    foreach( $results as $value )
                    {
                        list( $id, $subject ) = preg_split( '/:/', $value, 2 );
                        $return_array[$id] = trim( $subject );
                    }
                    return $return_array;
                }
                if( $format == 'l' )
                {
                    $results = preg_split( '/--/', $content );
                    foreach( $results as $value )
                    {
                        preg_match( '/id: ticket\/(\d+)/', $value, $match );
                        $id = $match[1];

                        preg_match_all("/(.+?): ([^\n]+)\n/", $content, $match);
                        $values = array_combine( $match[1], $match[2] );

                        // adjust some things
                        $values[id] = $id;
                        $values = preg_replace( '/Not set/', null, $values );

                        $return_array[$id] = $values;
                    }
                    return $return_array;
                }
                break;
        }
    }

    public function getTicket( $id )
    {
        $url = $this->rest_url . 'ticket/' . $id . '/show';
        $this->request->setURL( $url );
        $this->request->setMethod(HTTP_REQUEST_METHOD_GET);
        $this->request->clearPostData();
        $this->request->sendRequest();

        $response_code = $this->request->getResponseCode();
        $response_body = $this->request->getResponseBody();

        list( $code, $msg, $content ) = $this->splitResponseBody( $response_body );

        if( $response_code != 200 )
        {
            $this->error = $response_body;
            return false;
        }
        if( $code != 200 )
        {
            $this->error = "$msg\n$content";
            return false;
        }
        $this->error = '';

        if( $content == "# Ticket $id does not exist." )
        {
            return false;
        }

        preg_match_all("/(.+?): ?([^\n]*)\n?/", $content, $match);
        $return_array = array_combine( $match[1], $match[2] );

        // adjust some things
        $return_array['id'] = $id;
        $return_array = preg_replace( '/Not set/', null, $return_array );

        return $return_array;
    }

    public function getFirstTicketHistoryContent( $id )
    {
        $url = $this->rest_url . 'ticket/' . $id . '/history/type/create';
        $this->request->setURL( $url );
        $this->request->clearPostData();
        $this->request->addQueryString( 'format' , 'l' );
        $this->request->sendRequest();

        $response_code = $this->request->getResponseCode();
        $response_body = $this->request->getResponseBody();

        list( $code, $msg, $content ) = $this->splitResponseBody( $response_body );
        if( $response_code != 200 )
        {
            $this->error = $response_body;
            return false;
        }
        if( $code != 200 )
        {
            $this->error = "$msg\n$content";
            return false;
        }
        $this->error = '';

        $start = strpos( $content, 'Content: ' ) + 9;
        $end   = strpos( $content, 'Creator: ' );
        $return_str = substr( $content, $start, $end - $start );
        $return_str = trim( $return_str );
        $return_str = preg_replace( '/^ {9}/m', '', $return_str );

        return $return_str;
    }

    public function setTicketValues( $id, $values )
    {
        $url = $this->rest_url . 'ticket/' . $id . '/edit';
        $this->request->setURL( $url );
        $this->request->setMethod(HTTP_REQUEST_METHOD_POST);
        $this->request->clearPostData();
        $this->request->addPostData('content', $values );
        $this->request->sendRequest();

        $response_code = $this->request->getResponseCode();
        $response_body = $this->request->getResponseBody();

        list( $code, $msg, $content ) = $this->splitResponseBody( $response_body );
        if( $response_code != 200 )
        {
            $this->error = $response_body;
            return false;
        }
        if( $code != 200 )
        {
            $this->error = "$msg\n$content";
            return false;
        }

        return true;
    }

    public function addTicketComment( $id, $comment )
    {
        $comment = preg_replace( "/[\r\n]+/", "\n ", $comment );
        $comment = utf8_encode( $comment );
        $comment = "Action: comment\nText: $comment";

        $url = $this->rest_url . 'ticket/' . $id . '/comment';
        $this->request->setURL( $url );
        $this->request->setMethod(HTTP_REQUEST_METHOD_POST);
        $this->request->clearPostData();
        $this->request->addPostData('content', $comment );
        $this->request->sendRequest();

        $response_code = $this->request->getResponseCode();
        $response_body = $this->request->getResponseBody();

        list( $code, $msg, $content ) = $this->splitResponseBody( $response_body );
        if( $response_code != 200 )
        {
            $this->error = $response_body;
            return false;
        }
        if( $code != 200 )
        {
            $this->error = "$msg\n$content";
            return false;
        }

        return true;
    }

    private function splitResponseBody( $response_body )
    {
        list( $head, $content ) = preg_split( '/\n\n/', $response_body, 2 );

        preg_match_all( '/^RT\/\d+(?:\S+) (\d+) ([\w\s]+)$/', $head, $match );
        $code = $match[1][0];
        $msg    = $match[2][0];

        $msg = trim( $msg );
        $content = utf8_decode( trim( $content ) );

        $return_array = array( $code, $msg, $content );

        return $return_array;
    }
}
