# Recorded provider responses

Hand-written from the field names each provider publicly documents. **No fixture
here was captured from a live request**, and nothing in the test suite makes one
— this project's rules confine requests to the local install, and every provider
API is outside it.

That makes a fixture a statement of the shape we *believe* a provider sends. It
is enough to prove the provider layer generalises, and not enough to ship an
integration to a shelter. Each provider map records its own confidence; see the
gates on issue #40 for Adopt-a-Pet's.
