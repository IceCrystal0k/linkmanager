<?php
namespace App\Enums;

abstract class HttpCode extends BasicEnum
{
// 1xx: HTTP Informational Codes
    const continue  = 100;
    const SwitchingProtocols = 101;
    const Processing = 102;
    const Checkpoint = 103;
    const RequestURITooLongInfo = 122;

// 2xx: HTTP Successful Codes
    const OK = 200;
    const Created = 201;
    const Accepted = 202;
    const NonAuthoritativeInformation = 203;
    const NoContent = 204;
    const ResetContent = 205;
    const PartialContent = 206;
    const MultiStatus = 207;
    const AlreadyReported = 208;
    const IMUsed = 226;

// 3xx: HTTP Redire­ction Codes
    const MultipleChoices = 300;
    const MovedPermanently = 301;
    const Found = 302;
    const SeeOther = 303;
    const NotModified = 304;
    const UseProxy = 305;
    const SwitchProxy = 306;
    const TemporaryRedirect = 307;
    const PermanentRedirect = 308;
// 307 and 308 are similar to 302 and 301, but the new request method after redirect must be the same, as on initial request. = 307;

// 4xx: HTTP Client Error Code
    const BadRequest = 400;
    const Unauthorized = 401;
    const PaymentRequired = 402;
    const Forbidden = 403;
    const NotFound = 404;
    const MethodNotAllowed = 405;
    const NotAcceptable = 406;
    const ProxyAuthenticationRequired = 407;
    const RequestTimeout = 408;
    const Conflict = 409;
    const Gone = 410;
    const LengthRequired = 411;
    const PreconditionFailed = 412;
    const RequestEntityTooLarge = 413;
    const RequestURITooLong = 414;
    const UnsupportedMediaType = 415;
    const RequestedRangeNotSatisfiable = 416;
    const ExpectationFailed = 417;
    const ImATeapot = 418;
    const UnprocessableEntity = 422;
    const Locked = 423;
    const FailedDependency = 424;
    const UnorderedCollection = 425;
    const UpgradeRequired = 426;
    const PreconditionRequired = 428;
    const TooManyRequests = 429;
    const RequestHeaderFieldsTooLarge = 431;
    const NoResponse = 444;
    const RetryWith = 449;
    const BlockedByWindowsParentalControls = 450;
    const UnavailableForLegalReasons = 451;
    const ClientClosedRequest = 499;

// 5xx: HTTP Server Error Codes
    const InternalServerError = 500;
    const NotImplemented = 501;
    const BadGateway = 502;
    const ServiceUnavailable = 503;
    const GatewayTimeout = 504;
    const HTTPVersionNotSupported = 505;
    const VariantAlsoNegotiates = 506;
    const InsufficientStorage = 507;
    const LoopDetected = 508;
    const BandwidthLimitExceeded = 509;
    const NotExtended = 510;
    const NetworkAuthenticationRequired = 511;
    const NetworkRreadTimeoutError = 598;
    const NetworkConnectTimeoutError = 599;
}
