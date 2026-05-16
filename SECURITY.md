# Security Policy

## Supported Versions

| Version | Supported |
|---|---|
| 0.1.x | ✅ Yes |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Email the maintainer directly at **zilleali1245@gmail.com** with:

- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

You will receive a response within **48 hours**.

Once confirmed, a fix will be released as soon as possible and you will be
credited in the changelog unless you prefer to remain anonymous.

## Security Considerations

When using this package:

- Store router credentials in `.env` — never hardcode them
- Use API-SSL (port 8729) in production environments
- Create a dedicated read-only API user on the router for monitoring-only setups
- Restrict API access by IP in RouterOS: `IP → Services → api → Available From`

## Creating a Restricted API User on RouterOS

```
/user/group/add name=api-readonly policy=read,api,!write,!policy,!test,!winbox,!web,!ftp,!ssh,!telnet
/user/add name=laravel-api group=api-readonly password=strongpassword
```
