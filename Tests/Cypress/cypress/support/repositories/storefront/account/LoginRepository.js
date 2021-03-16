class LoginRepository {

  getEmail() {
    return cy.get('#email');
  }

  getPassword() {
    return cy.get('#passwort');
  }

  getSubmitButton() {
    return cy.get('.register--login-btn')
  }

}

export default LoginRepository;
