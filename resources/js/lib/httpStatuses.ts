export type HttpStatusPresentation = {
    category: 'Informational' | 'Success' | 'Redirection' | 'Client error' | 'Server error';
    title: string;
    message: string;
    diagnosis: string;
};

const HTTP_STATUSES: Record<number, Omit<HttpStatusPresentation, 'category'>> = {
    100: {
        title: 'Continue',
        message: 'The initial part of the request was accepted and the client may continue.',
        diagnosis: 'This is an interim protocol response and is not normally shown as a page.',
    },
    101: {
        title: 'Switching protocols',
        message: 'The server accepted a request to switch communication protocols.',
        diagnosis: 'The connection is transitioning to another protocol, such as a WebSocket connection.',
    },
    102: {
        title: 'Processing',
        message: 'The server accepted the request and is still processing it.',
        diagnosis: 'The operation may take longer than a normal request to complete.',
    },
    103: {
        title: 'Early hints',
        message: 'The server sent preliminary resource hints before the final response.',
        diagnosis: 'This interim response helps the browser begin loading related assets early.',
    },
    200: {
        title: 'OK',
        message: 'The request completed successfully.',
        diagnosis: 'The server returned the requested resource or completed the requested operation.',
    },
    201: {
        title: 'Created',
        message: 'The requested resource was created successfully.',
        diagnosis: 'A new record or resource now exists as a result of this request.',
    },
    202: {
        title: 'Accepted',
        message: 'The request was accepted for processing.',
        diagnosis: 'Processing may continue asynchronously after this response.',
    },
    203: {
        title: 'Non-authoritative information',
        message: 'The request succeeded, but some response information came from another source.',
        diagnosis: 'A proxy or intermediary modified or supplied part of the metadata.',
    },
    204: {
        title: 'No content',
        message: 'The request completed successfully without a response body.',
        diagnosis: 'The operation succeeded and there is no additional page content to display.',
    },
    205: {
        title: 'Reset content',
        message: 'The request succeeded and the current input view should be reset.',
        diagnosis: 'The client should clear or reset the form that produced the request.',
    },
    206: {
        title: 'Partial content',
        message: 'The server returned only the requested portion of the resource.',
        diagnosis: 'The response was produced from a valid byte-range request.',
    },
    207: {
        title: 'Multi-status',
        message: 'The response contains separate results for multiple operations.',
        diagnosis: 'Each contained operation may have its own independent status.',
    },
    208: {
        title: 'Already reported',
        message: 'This resource was already included earlier in the same multi-status response.',
        diagnosis: 'The server omitted duplicate details for the same resource binding.',
    },
    226: {
        title: 'IM used',
        message: 'The server fulfilled the request using one or more instance manipulations.',
        diagnosis: 'The returned representation was produced using negotiated delta or transformation handling.',
    },
    300: {
        title: 'Multiple choices',
        message: 'More than one representation or destination is available.',
        diagnosis: 'The client must choose which resource or representation to open.',
    },
    301: {
        title: 'Moved permanently',
        message: 'This resource has permanently moved to a different address.',
        diagnosis: 'Clients and saved links should use the replacement URL.',
    },
    302: {
        title: 'Found',
        message: 'The requested resource is temporarily available at another address.',
        diagnosis: 'The browser normally follows the redirect automatically.',
    },
    303: {
        title: 'See other',
        message: 'The result should be retrieved from another address.',
        diagnosis: 'The browser should follow the provided location using a GET request.',
    },
    304: {
        title: 'Not modified',
        message: 'The cached resource is still current.',
        diagnosis: 'The browser may reuse its stored copy instead of downloading the content again.',
    },
    305: {
        title: 'Use proxy',
        message: 'The requested resource must be accessed through a proxy.',
        diagnosis: 'This status is deprecated and should not normally be produced by modern applications.',
    },
    307: {
        title: 'Temporary redirect',
        message: 'The resource is temporarily available at another address.',
        diagnosis: 'The browser should repeat the same request method at the provided location.',
    },
    308: {
        title: 'Permanent redirect',
        message: 'The resource has permanently moved to another address.',
        diagnosis: 'The browser should repeat the same request method at the replacement location.',
    },
    400: {
        title: 'Bad request',
        message: 'The server could not understand this request.',
        diagnosis: 'Some request data was missing, malformed, or not valid for this page.',
    },
    401: {
        title: 'Sign in required',
        message: 'You need to sign in before opening this page.',
        diagnosis: 'Your session may have expired, or this page requires an authenticated account.',
    },
    402: {
        title: 'Payment required',
        message: 'This request requires a payment or billing action before it can continue.',
        diagnosis: 'The requested resource is currently restricted by a payment requirement.',
    },
    403: {
        title: 'Access denied',
        message: 'You do not have permission to open this page or perform this action.',
        diagnosis: 'Your current account role or record ownership does not satisfy the required permission.',
    },
    404: {
        title: 'Page not found',
        message: 'The page or record you are looking for could not be found.',
        diagnosis: 'The link may be outdated, the record may have been removed, or the route does not exist.',
    },
    405: {
        title: 'Method not allowed',
        message: 'This page does not support the request method that was used.',
        diagnosis: 'The route exists, but it does not accept this kind of request.',
    },
    406: {
        title: 'Response not acceptable',
        message: 'The server cannot return this resource in the requested format.',
        diagnosis: 'The requested response format is not available for this endpoint.',
    },
    407: {
        title: 'Proxy authentication required',
        message: 'A network proxy requires authentication before this request can continue.',
        diagnosis: 'The connection was stopped by a proxy between your device and the application.',
    },
    408: {
        title: 'Request timed out',
        message: 'The server waited too long for the request to finish.',
        diagnosis: 'The connection may be slow or the request may have taken too long to send.',
    },
    409: {
        title: 'Request conflict',
        message: 'This request conflicts with the current state of the record.',
        diagnosis: 'The data may have changed, already been processed, or been updated by another request.',
    },
    410: {
        title: 'Resource no longer available',
        message: 'The requested resource existed previously but is no longer available.',
        diagnosis: 'The record or endpoint has been permanently removed.',
    },
    411: {
        title: 'Request length required',
        message: 'The server requires the request size before it can process this request.',
        diagnosis: 'The request did not include the required content-length information.',
    },
    412: {
        title: 'Precondition failed',
        message: 'A required condition for this request was not satisfied.',
        diagnosis: 'The record state or request headers no longer match the expected condition.',
    },
    413: {
        title: 'Upload too large',
        message: 'The submitted file or request is larger than the allowed limit.',
        diagnosis: 'Reduce the file size or split the request before trying again.',
    },
    414: {
        title: 'Address too long',
        message: 'The requested URL is longer than the server can process.',
        diagnosis: 'The link contains too much path or query information.',
    },
    415: {
        title: 'Unsupported file or media type',
        message: 'The server does not support the submitted content type.',
        diagnosis: 'Use one of the file or request formats accepted by this feature.',
    },
    416: {
        title: 'Requested range unavailable',
        message: 'The requested part of the resource cannot be returned.',
        diagnosis: 'The requested byte range is outside the available content.',
    },
    417: {
        title: 'Request expectation failed',
        message: 'The server could not satisfy an expectation included with the request.',
        diagnosis: 'A request header asked for behavior that this server cannot provide.',
    },
    418: {
        title: 'Request refused',
        message: 'The server deliberately refused this unusual request.',
        diagnosis: 'This status is normally used for diagnostics or intentionally unsupported behavior.',
    },
    421: {
        title: 'Misdirected request',
        message: 'This request reached a server that cannot produce the expected response.',
        diagnosis: 'The hostname, connection, or proxy routing may be pointing to the wrong service.',
    },
    422: {
        title: 'Request could not be processed',
        message: 'The request was understood, but its content could not be accepted.',
        diagnosis: 'One or more values violate the business rules required by this action.',
    },
    423: {
        title: 'Resource locked',
        message: 'This record is currently locked and cannot be changed.',
        diagnosis: 'Another process, workflow state, or protection rule is preventing modifications.',
    },
    424: {
        title: 'Dependent action failed',
        message: 'This request could not finish because another required action failed.',
        diagnosis: 'A dependency needed by this operation did not complete successfully.',
    },
    425: {
        title: 'Request sent too early',
        message: 'The server is not ready to process this request safely yet.',
        diagnosis: 'Wait briefly and retry after the previous operation or connection is fully established.',
    },
    426: {
        title: 'Upgrade required',
        message: 'A newer or different protocol is required to continue.',
        diagnosis: 'The current client connection does not meet the protocol requirements of this endpoint.',
    },
    428: {
        title: 'Precondition required',
        message: 'This request must include a condition before the server can process it.',
        diagnosis: 'The server requires protection against overwriting a record that may have changed.',
    },
    429: {
        title: 'Too many requests',
        message: 'This action was attempted too many times in a short period.',
        diagnosis: 'Rate limiting is active to protect the application. Wait before trying again.',
    },
    431: {
        title: 'Request headers too large',
        message: 'The browser sent more header or cookie data than the server can accept.',
        diagnosis: 'Clearing stale site cookies or starting a new browser session may resolve the problem.',
    },
    451: {
        title: 'Unavailable for legal reasons',
        message: 'This resource cannot be provided because of a legal restriction.',
        diagnosis: 'Access is blocked by a legal, policy, or regulatory requirement.',
    },
    500: {
        title: 'Internal server error',
        message: 'The application encountered an unexpected server-side problem.',
        diagnosis: 'The request reached the application, but an internal operation failed.',
    },
    501: {
        title: 'Feature not implemented',
        message: 'The server does not support the requested functionality.',
        diagnosis: 'This method or feature has not been implemented by the application or server.',
    },
    502: {
        title: 'Bad gateway',
        message: 'A gateway received an invalid response from an upstream service.',
        diagnosis: 'The application server, proxy, or another required service may be unavailable.',
    },
    503: {
        title: 'Service unavailable',
        message: 'The application is temporarily unavailable.',
        diagnosis: 'The service may be in maintenance mode, restarting, or temporarily overloaded.',
    },
    504: {
        title: 'Gateway timed out',
        message: 'A gateway waited too long for another service to respond.',
        diagnosis: 'An upstream application, database, or network service did not answer in time.',
    },
    505: {
        title: 'HTTP version not supported',
        message: 'The server does not support the HTTP version used by this request.',
        diagnosis: 'The client or an intermediate proxy is using an unsupported protocol version.',
    },
    506: {
        title: 'Content negotiation error',
        message: 'The server configuration caused a response-selection conflict.',
        diagnosis: 'The available response variants reference each other incorrectly.',
    },
    507: {
        title: 'Insufficient storage',
        message: 'The server does not currently have enough storage to complete this request.',
        diagnosis: 'A file, cache, session, log, or database operation could not reserve enough space.',
    },
    508: {
        title: 'Processing loop detected',
        message: 'The server detected a loop while trying to complete this request.',
        diagnosis: 'A recursive dependency or repeated internal redirect prevented completion.',
    },
    510: {
        title: 'Additional capability required',
        message: 'The request requires an extension that was not provided.',
        diagnosis: 'The server needs additional request information or functionality to continue.',
    },
    511: {
        title: 'Network authentication required',
        message: 'The current network requires authentication before the application can be reached.',
        diagnosis: 'Sign in to the network or captive portal, then reload this page.',
    },
};

function statusCategory(status: number): HttpStatusPresentation['category'] {
    if (status < 200) return 'Informational';
    if (status < 300) return 'Success';
    if (status < 400) return 'Redirection';
    if (status < 500) return 'Client error';
    return 'Server error';
}

export function httpStatusPresentation(status: number, statusText?: string | null): HttpStatusPresentation {
    const configured = HTTP_STATUSES[status];
    const category = statusCategory(status);

    if (configured) {
        return { category, ...configured };
    }

    const title = statusText?.trim() || `${category} response`;

    if (status < 200) {
        return {
            category,
            title,
            message: 'The server returned an informational response.',
            diagnosis: 'This is an interim protocol status and is not normally rendered as a standalone page.',
        };
    }

    if (status < 300) {
        return {
            category,
            title,
            message: 'The request completed successfully.',
            diagnosis: 'This is an uncommon successful HTTP status without a dedicated application message.',
        };
    }

    if (status < 400) {
        return {
            category,
            title,
            message: 'The request is being redirected or handled through another resource.',
            diagnosis: 'This is an uncommon redirection status without a dedicated application message.',
        };
    }

    if (status < 500) {
        return {
            category,
            title,
            message: 'The application could not complete this request.',
            diagnosis: 'An uncommon client-side HTTP error occurred. Check the request and try again.',
        };
    }

    return {
        category,
        title,
        message: 'The server could not complete this request.',
        diagnosis: 'An uncommon server-side error occurred. Try again later or contact an administrator.',
    };
}
